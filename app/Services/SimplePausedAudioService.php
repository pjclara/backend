<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Simple Paused Audio Service
 * 
 * Uses comma insertion to create natural pauses in Google TTS
 * Much faster and simpler than FFmpeg concatenation
 */
class SimplePausedAudioService
{
    protected float $speed = 0.9;
    protected int $bitrate = 128; // kbps
    protected int $sampleRate = 44100;
    
    /**
     * Words that should NOT have a pause after them (monosyllabic and connectors)
     */
    protected array $noPauseWords = [
        // Articles
        'a', 'o', 'os', 'as', 'um', 'uma', 'uns', 'umas',
        // Prepositions
        'de', 'do', 'da', 'dos', 'das', 'em', 'no', 'na', 'nos', 'nas',
        'por', 'ao', 'à', 'aos', 'às',
        // Conjunctions
        'e', 'ou', 'que', 'se',
        // Other monosyllabic words
        'é', 'há', 'já', 'me', 'te', 'se', 'lhe', 'nos', 'vos',
        'seu', 'sua', 'meu', 'teu',
    ];
    
    /**
     * Transform sentence by inserting commas between words
     * Skip commas after monosyllabic words and connectors
     * 
     * Example:
     * Input:  "A menina vê a mamã"
     * Output: "A menina, vê, a mamã"
     * 
     * @param string $sentence Original sentence
     * @return string Sentence with commas inserted
     */
    public function insertCommasForPauses(string $sentence): string
    {
        $sentence = trim($sentence);
        
        // Remove existing punctuation at the end
        $sentence = preg_replace('/[.,;:!?¿¡]+$/', '', $sentence);
        
        // Split into words (preserve internal punctuation like "D'Ávila")
        $words = preg_split('/\s+/', $sentence, -1, PREG_SPLIT_NO_EMPTY);
        
        if (empty($words)) {
            return $sentence;
        }
        
        // Join words with commas (skip monosyllabic and connector words)
        $result = '';
        $lastIndex = count($words) - 1;
        
        for ($i = 0; $i <= $lastIndex; $i++) {
            $word = $words[$i];
            $wordLower = mb_strtolower($word);
            $isLastWord = ($i === $lastIndex);
            
            // Add the word
            $result .= $word;
            
            if (!$isLastWord) {
                // Check if next word exists and if current word should have pause
                $shouldPause = !in_array($wordLower, $this->noPauseWords);
                
                // Also check if it's a very short word (1-2 letters) - likely monosyllabic
                if (mb_strlen($wordLower) <= 2) {
                    $shouldPause = false;
                }
                
                // Add comma if should pause, otherwise just space
                $result .= $shouldPause ? ', ' : ' ';
            }
        }
        
        return $result;
    }
    
    /**
     * Generate sentence audio with natural pauses using comma insertion
     * 
     * @param string $sentence The sentence to convert
     * @param string $lang Language code (default: pt-PT)
     * @param bool $insertPauses Whether to insert commas for pauses
     * @param float|null $speed Audio speed multiplier (default: 0.9)
     * @param int|null $exerciseNumber Exercise number for filename
     * @return string|null Path to generated audio file
     */
    public function generateSentenceAudio(
        string $sentence,
        string $lang = 'pt-PT',
        bool $insertPauses = true,
        ?float $speed = null,
        ?int $exerciseNumber = null
    ): ?string {
        $sentence = trim($sentence);
        if (empty($sentence)) {
            return null;
        }
        
        $speed = $speed ?? $this->speed;
        $originalSentence = $sentence;
        
        // Transform sentence with commas if requested
        if ($insertPauses) {
            $sentence = $this->insertCommasForPauses($sentence);
        }
        
        // Generate filename based on original sentence
        $filename = $this->generateFilename($originalSentence, $insertPauses, $speed, $exerciseNumber);
        $finalPath = "audio/sentences/{$filename}";
        
        // Check if already exists
        if (Storage::disk('public')->exists($finalPath)) {
            return $finalPath;
        }
        
        try {
            // Fetch TTS audio from Google Translate
            $audioData = $this->fetchGoogleTTS($sentence, $lang);
            
            if (!$audioData) {
                Log::warning("Failed to fetch TTS for: {$originalSentence}");
                return null;
            }
            
            // If speed is not default, process with FFmpeg
            if ($speed !== 1.0) {
                $audioData = $this->processAudioSpeed($audioData, $speed);
            }
            
            if (!$audioData) {
                return null;
            }
            
            // Save to storage
            $destinationPath = "audio/sentences";
            if (!Storage::disk('public')->exists($destinationPath)) {
                Storage::disk('public')->makeDirectory($destinationPath);
            }
            
            Storage::disk('public')->put($finalPath, $audioData);
            
            return $finalPath;
            
        } catch (\Exception $e) {
            Log::error('Error generating audio: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Fetch TTS audio from Google Translate
     * 
     * @param string $text Text to convert (can include commas for pauses)
     * @param string $lang Language code
     * @return string|null Audio data (MP3)
     */
    protected function fetchGoogleTTS(string $text, string $lang): ?string
    {
        try {
            $encodedText = urlencode($text);
            $url = "https://translate.google.com/translate_tts?ie=UTF-8&tl={$lang}&client=tw-ob&q={$encodedText}";
            
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Referer' => 'https://translate.google.com/',
            ])->timeout(15)->get($url);
            
            if ($response->successful() && strlen($response->body()) > 100) {
                return $response->body();
            }
            
            // Retry once if failed
            sleep(1);
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Referer' => 'https://translate.google.com/',
            ])->timeout(15)->get($url);
            
            if ($response->successful() && strlen($response->body()) > 100) {
                return $response->body();
            }
            
        } catch (\Exception $e) {
            Log::warning('TTS fetch failed: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Process audio speed using FFmpeg
     * 
     * @param string $audioData Input audio data
     * @param float $speed Speed multiplier (0.5 = slow, 1.0 = normal, 1.5 = fast)
     * @return string|null Processed audio data
     */
    protected function processAudioSpeed(string $audioData, float $speed): ?string
    {
        $ffmpegPath = trim(\shell_exec('which ffmpeg') ?? '');
        
        if (empty($ffmpegPath)) {
            Log::warning('FFmpeg not found, returning original audio');
            return $audioData;
        }
        
        try {
            // Create temp files
            $tempDir = storage_path('app/temp');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            $inputFile = $tempDir . '/' . uniqid('input_') . '.mp3';
            $outputFile = $tempDir . '/' . uniqid('output_') . '.mp3';
            
            // Save input audio
            file_put_contents($inputFile, $audioData);
            
            // Process with FFmpeg: apply speed and normalize volume
            $cmd = sprintf(
                '%s -i %s -filter:a "atempo=%s,loudnorm" -ar %d -b:a %dk %s -y 2>&1',
                escapeshellarg($ffmpegPath),
                escapeshellarg($inputFile),
                $speed,
                $this->sampleRate,
                $this->bitrate,
                escapeshellarg($outputFile)
            );
            
            exec($cmd, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($outputFile)) {
                $processedData = file_get_contents($outputFile);
                
                // Cleanup
                @unlink($inputFile);
                @unlink($outputFile);
                
                return $processedData;
            }
            
            // Cleanup on failure
            @unlink($inputFile);
            @unlink($outputFile);
            
            Log::warning('FFmpeg processing failed, returning original audio');
            return $audioData;
            
        } catch (\Exception $e) {
            Log::error('Audio processing error: ' . $e->getMessage());
            return $audioData;
        }
    }
    
    /**
     * Generate unique filename for audio
     * 
     * @param string $sentence Original sentence
     * @param bool $withPauses Whether pauses were inserted
     * @param float $speed Speed multiplier
     * @param int|null $exerciseNumber Exercise number
     * @return string Filename
     */
    protected function generateFilename(string $sentence, bool $withPauses, float $speed, ?int $exerciseNumber = null): string
    {
        $slug = Str::slug(substr($sentence, 0, 40));
        
        // If exercise number provided, use format: exercise-{number}-{slug}.mp3
        if ($exerciseNumber !== null) {
            return "exercise-{$exerciseNumber}-{$slug}.mp3";
        }
        
        // Legacy format for backwards compatibility
        $pauseFlag = $withPauses ? 'paused' : 'normal';
        $speedStr = str_replace('.', '', (string)$speed);
        $hash = substr(md5($sentence . $pauseFlag . $speed), 0, 8);
        
        return "{$slug}_{$pauseFlag}_{$speedStr}x_{$hash}.mp3";
    }
    
    /**
     * Batch generate audio for multiple sentences
     * 
     * @param array $sentences Array of sentences
     * @param string $lang Language code
     * @param bool $insertPauses Whether to insert commas
     * @param float|null $speed Speed multiplier
     * @return array Array of results ['sentence' => 'path']
     */
    public function batchGenerate(
        array $sentences,
        string $lang = 'pt-PT',
        bool $insertPauses = true,
        ?float $speed = null
    ): array {
        $results = [];
        
        foreach ($sentences as $sentence) {
            $path = $this->generateSentenceAudio($sentence, $lang, $insertPauses, $speed);
            $results[$sentence] = $path;
            
            // Small delay to avoid rate limiting
            usleep(200000); // 0.2 seconds
        }
        
        return $results;
    }
    
    /**
     * Check if sentence already has audio generated
     * 
     * @param string $sentence Sentence to check
     * @param bool $insertPauses Pause setting
     * @param float $speed Speed setting
     * @param int|null $exerciseNumber Exercise number
     * @return bool
     */
    public function audioExists(string $sentence, bool $insertPauses = true, float $speed = 0.9, ?int $exerciseNumber = null): bool
    {
        $filename = $this->generateFilename($sentence, $insertPauses, $speed, $exerciseNumber);
        return Storage::disk('public')->exists("audio/sentences/{$filename}");
    }
}
