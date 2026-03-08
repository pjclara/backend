<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Enhanced Audio Service with word-by-word TTS and configurable pauses
 * 
 * Features:
 * - Word-level audio generation with pauses
 * - Caching for common words (performance optimization)
 * - Volume normalization (loudnorm)
 * - Configurable speed and pause duration
 * - Clean error handling
 */
class EnhancedAudioService
{
    protected string $ffmpegPath;
    protected string $storagePath;
    protected float $speed = 0.9;
    protected float $pauseDuration = 0.3; // seconds
    protected int $sampleRate = 44100;
    protected int $bitrate = 128; // kbps
    
    public function __construct()
    {
        $this->ffmpegPath = trim(\shell_exec('which ffmpeg') ?? '');
        $this->storagePath = storage_path('app/public');
        
        if (empty($this->ffmpegPath)) {
            throw new \RuntimeException('FFmpeg not found. Please install FFmpeg.');
        }
    }
    
    /**
     * Generate sentence audio with pauses between words
     * 
     * @param string $sentence The sentence to convert
     * @param string $lang Language code (default: pt-PT)
     * @param float|null $pauseDuration Pause duration in seconds (default: 0.3)
     * @param float|null $speed Audio speed multiplier (default: 0.9)
     * @return string|null Path to generated audio file
     */
    public function generateSentenceWithPauses(
        string $sentence,
        string $lang = 'pt-PT',
        ?float $pauseDuration = null,
        ?float $speed = null
    ): ?string {
        $sentence = trim($sentence);
        if (empty($sentence)) {
            return null;
        }
        
        $pauseDuration = $pauseDuration ?? $this->pauseDuration;
        $speed = $speed ?? $this->speed;
        
        // Create unique filename
        $filename = $this->generateFilename($sentence, $pauseDuration, $speed);
        $finalPath = "audio/sentences/{$filename}";
        
        // Check if already exists
        if (Storage::disk('public')->exists($finalPath)) {
            return $finalPath;
        }
        
        // Create temp directory
        $tempDir = $this->storagePath . '/audio/temp/' . uniqid('sentence_');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        try {
            // Extract words
            $words = $this->extractWords($sentence);
            if (empty($words)) {
                return null;
            }
            
            // Generate audio for each word
            $wordFiles = $this->generateWordAudios($words, $lang, $speed, $tempDir);
            if (empty($wordFiles)) {
                $this->cleanup($tempDir);
                return null;
            }
            
            // Generate silence file
            $silenceFile = $this->generateSilence($pauseDuration, $tempDir);
            if (!$silenceFile) {
                $this->cleanup($tempDir);
                return null;
            }
            
            // Concatenate
            $finalFile = $this->concatenateAudio($wordFiles, $silenceFile, $tempDir);
            if (!$finalFile) {
                $this->cleanup($tempDir);
                return null;
            }
            
            // Move to final location
            $destinationPath = $this->storagePath . '/' . $finalPath;
            $destinationDir = dirname($destinationPath);
            
            if (!file_exists($destinationDir)) {
                mkdir($destinationDir, 0755, true);
            }
            
            copy($finalFile, $destinationPath);
            $this->cleanup($tempDir);
            
            return $finalPath;
            
        } catch (\Exception $e) {
            Log::error('Error generating sentence audio: ' . $e->getMessage());
            $this->cleanup($tempDir);
            return null;
        }
    }
    
    /**
     * Extract words from sentence (remove punctuation)
     */
    protected function extractWords(string $text): array
    {
        $text = preg_replace('/[.,;:!?¿¡()\[\]{}"\'«»\-–—…\/\\\\]/', ' ', $text);
        return preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    }
    
    /**
     * Generate audio files for multiple words
     */
    protected function generateWordAudios(array $words, string $lang, float $speed, string $tempDir): array
    {
        $wordFiles = [];
        
        foreach ($words as $index => $word) {
            $wordLower = mb_strtolower($word);
            
            // Check if word audio is cached
            $cachedPath = $this->getCachedWordAudio($wordLower, $lang, $speed);
            if ($cachedPath) {
                $wordFiles[] = $cachedPath;
                continue;
            }
            
            // Generate new word audio
            $audioData = $this->fetchTTS($word, $lang);
            if (!$audioData) {
                continue;
            }
            
            // Save raw audio
            $rawFile = $tempDir . '/word_' . $index . '_raw.mp3';
            file_put_contents($rawFile, $audioData);
            
            // Process audio (speed + normalize)
            $processedFile = $tempDir . '/word_' . $index . '.mp3';
            if ($this->processAudio($rawFile, $processedFile, $speed)) {
                $wordFiles[] = $processedFile;
                unlink($rawFile);
                
                // Cache this word for future use
                $this->cacheWordAudio($wordLower, $processedFile, $lang, $speed);
            }
            
            usleep(150000); // 0.15s delay between API calls
        }
        
        return $wordFiles;
    }
    
    /**
     * Fetch TTS audio from Google Translate
     */
    protected function fetchTTS(string $text, string $lang): ?string
    {
        try {
            $encodedText = urlencode($text);
            $url = "https://translate.google.com/translate_tts?ie=UTF-8&tl={$lang}&client=tw-ob&q={$encodedText}";
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_REFERER, 'https://translate.google.com/');
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $audioData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && strlen($audioData) > 100) {
                return $audioData;
            }
        } catch (\Exception $e) {
            Log::warning('TTS fetch failed: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Process audio: apply speed and normalize volume
     */
    protected function processAudio(string $inputFile, string $outputFile, float $speed): bool
    {
        $cmd = sprintf(
            '%s -i %s -filter:a "atempo=%s,loudnorm" -ar %d -b:a %dk %s -y 2>&1',
            escapeshellarg($this->ffmpegPath),
            escapeshellarg($inputFile),
            $speed,
            $this->sampleRate,
            $this->bitrate,
            escapeshellarg($outputFile)
        );
        
        exec($cmd, $output, $returnCode);
        
        return $returnCode === 0 && file_exists($outputFile);
    }
    
    /**
     * Generate silence audio file
     */
    protected function generateSilence(float $duration, string $tempDir): ?string
    {
        $silenceFile = $tempDir . '/silence.mp3';
        
        $cmd = sprintf(
            '%s -f lavfi -i anullsrc=r=%d:cl=stereo -t %s -q:a 2 -acodec libmp3lame %s -y 2>&1',
            escapeshellarg($this->ffmpegPath),
            $this->sampleRate,
            $duration,
            escapeshellarg($silenceFile)
        );
        
        exec($cmd, $output, $returnCode);
        
        return ($returnCode === 0 && file_exists($silenceFile)) ? $silenceFile : null;
    }
    
    /**
     * Concatenate word audio files with silence using FFmpeg concat demuxer
     */
    protected function concatenateAudio(array $wordFiles, string $silenceFile, string $tempDir): ?string
    {
        // Create concat list
        $concatList = $tempDir . '/concat_list.txt';
        $concatContent = '';
        
        foreach ($wordFiles as $idx => $wordFile) {
            $concatContent .= "file '" . basename($wordFile) . "'\n";
            // Add silence between words (not after last word)
            if ($idx < count($wordFiles) - 1) {
                $concatContent .= "file '" . basename($silenceFile) . "'\n";
            }
        }
        
        file_put_contents($concatList, $concatContent);
        
        // Concatenate
        $finalFile = $tempDir . '/final.mp3';
        $cmd = sprintf(
            'cd %s && %s -f concat -safe 0 -i concat_list.txt -c copy %s -y 2>&1',
            escapeshellarg($tempDir),
            escapeshellarg($this->ffmpegPath),
            escapeshellarg('final.mp3')
        );
        
        exec($cmd, $output, $returnCode);
        
        return ($returnCode === 0 && file_exists($finalFile)) ? $finalFile : null;
    }
    
    /**
     * Get cached word audio if exists
     */
    protected function getCachedWordAudio(string $word, string $lang, float $speed): ?string
    {
        $cacheKey = md5($word . $lang . $speed);
        $cachePath = "audio/cache/words/{$cacheKey}.mp3";
        
        if (Storage::disk('public')->exists($cachePath)) {
            return $this->storagePath . '/' . $cachePath;
        }
        
        return null;
    }
    
    /**
     * Cache word audio for reuse
     */
    protected function cacheWordAudio(string $word, string $audioFile, string $lang, float $speed): void
    {
        // Only cache common short words (2-5 chars)
        if (strlen($word) < 2 || strlen($word) > 5) {
            return;
        }
        
        $cacheKey = md5($word . $lang . $speed);
        $cachePath = $this->storagePath . "/audio/cache/words";
        
        if (!file_exists($cachePath)) {
            mkdir($cachePath, 0755, true);
        }
        
        $destination = $cachePath . "/{$cacheKey}.mp3";
        @copy($audioFile, $destination);
    }
    
    /**
     * Generate unique filename for sentence audio
     */
    protected function generateFilename(string $sentence, float $pause, float $speed): string
    {
        $slug = \Illuminate\Support\Str::slug(substr($sentence, 0, 50));
        $hash = substr(md5($sentence . $pause . $speed), 0, 8);
        return "{$slug}_{$hash}.mp3";
    }
    
    /**
     * Clean up temporary files
     */
    protected function cleanup(string $tempDir): void
    {
        if (file_exists($tempDir)) {
            @array_map('unlink', glob($tempDir . '/*'));
            @rmdir($tempDir);
        }
    }
}
