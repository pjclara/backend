<?php

namespace App\Filament\Resources\Words\Tables;

use App\Services\AudioService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('word')
                    ->searchable(),
                ViewColumn::make('audio_url')
                    ->label('Áudio')
                    ->view('filament.columns.audio-player'),
                TextColumn::make('difficulty')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                \Filament\Actions\Action::make('listen')
                    ->label('Ouvir')
                    ->icon('heroicon-o-speaker-wave')
                    ->color('info')
                    ->action(function ($record, $livewire) {
                        $audioUrl = $record->audio_url;
                        
                        // Se tem URL mas ficheiro não existe ou não há URL, tenta gerar
                        if (empty($audioUrl) || !Storage::disk('public')->exists(ltrim($audioUrl, '/'))) {
                            try {
                                // Se tem URL mas ficheiro não existe, extrai o nome do ficheiro
                                $filenamePrefix = null;
                                if ($audioUrl) {
                                    $filename = basename($audioUrl);
                                    $filenamePrefix = str_replace('.mp3', '', $filename);
                                }
                                
                                $audioUrl = AudioService::generateAndSave(
                                    $record->word,
                                    'pt-PT',
                                    'words',
                                    $filenamePrefix
                                );
                                
                                if ($audioUrl) {
                                    $record->update(['audio_url' => $audioUrl]);
                                }
                            } catch (\Exception $e) {
                                Log::error('Erro ao gerar áudio: ' . $e->getMessage());
                            }
                        }
                        
                        // Reproduz o áudio se houver
                        if ($audioUrl) {
                            $audioPath = ltrim($audioUrl, '/');
                            $url = asset('storage/' . $audioPath);
                            $url = str_replace("'", "\\'", $url);
                            $word = str_replace("'", "\\'", $record->word);
                            $livewire->js("
                                const audio = new Audio('{$url}');
                                audio.onerror = function() {
                                    console.warn('Áudio não encontrado para a palavra \"{$word}\", usando síntese de voz');
                                    window.speechSynthesis.cancel();
                                    const utterance = new SpeechSynthesisUtterance('{$word}');
                                    utterance.lang = 'pt-PT';
                                    utterance.rate = 0.85;
                                    window.speechSynthesis.speak(utterance);
                                };
                                audio.play().catch(error => {
                                    console.warn('Erro ao reproduzir áudio para a palavra \"{$word}\":', error);
                                    window.speechSynthesis.cancel();
                                    const utterance = new SpeechSynthesisUtterance('{$word}');
                                    utterance.lang = 'pt-PT';
                                    utterance.rate = 0.85;
                                    window.speechSynthesis.speak(utterance);
                                });
                            ");
                        } else {
                            // Fallback para síntese de voz se não há áudio
                            $word = str_replace("'", "\\'", $record->word);
                            $livewire->js("
                                window.speechSynthesis.cancel();
                                const utterance = new SpeechSynthesisUtterance('{$word}');
                                utterance.lang = 'pt-PT';
                                utterance.rate = 0.85;
                                window.speechSynthesis.speak(utterance);
                            ");
                        }
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
