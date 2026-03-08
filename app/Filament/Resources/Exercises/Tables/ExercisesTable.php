<?php

namespace App\Filament\Resources\Exercises\Tables;

use App\Enums\DictationDifficulty;
use App\Services\AudioService;
use App\Services\SimplePausedAudioService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExercisesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('#')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('sentence')
                    ->label('Exercício')
                    ->limit(60)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('words_count')
                    ->label('Palavras')
                    ->counts('words')
                    ->sortable(),
                TextColumn::make('difficulty')
                    ->label('Dificuldade')
                    ->formatStateUsing(fn($state): string => $state instanceof DictationDifficulty ? $state->label() : (string) $state)
                    ->badge()
                    ->color(fn($state): string => match ($state) {
                        DictationDifficulty::EASY => 'success',
                        DictationDifficulty::MEDIUM => 'warning',
                        DictationDifficulty::HARD => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_by')
                    ->label('Criado por')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                SelectFilter::make('difficulty')
                    ->label('Dificuldade')
                    ->options(collect(DictationDifficulty::cases())->mapWithKeys(fn($case) => [$case->value => $case->label()])->toArray()),
                SelectFilter::make('created_by')
                    ->label('Criado por')
                    ->options(function () {
                        return \App\Models\Exercise::query()
                            ->whereNotNull('created_by')
                            ->distinct()
                            ->pluck('created_by', 'created_by')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('listen')
                    ->label('Ouvir')
                    ->icon('heroicon-o-speaker-wave')
                    ->color('info')
                    ->action(function ($record, $livewire) {
                        $audioUrl = null;
                        
                        // 1. Primeiro, verifica se já tem audio_url_1 válido em sentences/
                        if (!empty($record->audio_url_1) && 
                            str_starts_with($record->audio_url_1, 'audio/sentences/') &&
                            Storage::disk('public')->exists($record->audio_url_1)) {
                            $audioUrl = $record->audio_url_1;

                            
                        }
                        
                        // 2. Se não tem, tenta encontrar o arquivo baseado no número do exercício
                        if (!$audioUrl && $record->number) {
                            $slug = \Illuminate\Support\Str::slug(substr($record->sentence, 0, 40));
                            $possiblePaths = [
                                "audio/sentences/exercise-{$record->number}-{$slug}.mp3",
                                "audio/sentences/exercise-{$record->number}.mp3",
                                "audio/sentences/{$record->number}.mp3",
                                "audio/sentences/sentence-{$record->number}.mp3",
                            ];
                            
                            foreach ($possiblePaths as $path) {
                                if (Storage::disk('public')->exists($path)) {
                                    $audioUrl = $path;
                                    // Atualiza o registro com o caminho correto
                                    $record->update(['audio_url_1' => $audioUrl]);
                                    break;
                                }
                            }
                        }
                        
                        // 3. Se ainda não encontrou, tenta gerar novo áudio no formato correto
                        if (!$audioUrl) {
                            try {
                                // Usar SimplePausedAudioService (novo formato com pausas)
                                $pausedService = new SimplePausedAudioService();
                                $audioUrl = $pausedService->generateSentenceAudio(
                                    $record->sentence,
                                    'pt-PT',
                                    true,
                                    0.9,
                                    $record->number
                                );
                                
                                if ($audioUrl) {
                                    $record->update(['audio_url_1' => $audioUrl]);
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
                            $sentence = str_replace("'", "\\'", $record->sentence);
                            $livewire->js("
                                const audio = new Audio('{$url}');
                                audio.onerror = function() {
                                    console.warn('Áudio não encontrado para exercício {$record->number}, usando síntese de voz');
                                    window.speechSynthesis.cancel();
                                    const utterance = new SpeechSynthesisUtterance('{$sentence}');
                                    utterance.lang = 'pt-PT';
                                    utterance.rate = 0.85;
                                    window.speechSynthesis.speak(utterance);
                                };
                                audio.play().catch(error => {
                                    console.warn('Erro ao reproduzir áudio para exercício {$record->number}:', error);
                                    window.speechSynthesis.cancel();
                                    const utterance = new SpeechSynthesisUtterance('{$sentence}');
                                    utterance.lang = 'pt-PT';
                                    utterance.rate = 0.85;
                                    window.speechSynthesis.speak(utterance);
                                });
                            ");
                        } else {
                            // Fallback para síntese de voz se não há áudio
                            $sentence = str_replace("'", "\\'", $record->sentence);
                            $livewire->js("
                                window.speechSynthesis.cancel();
                                const utterance = new SpeechSynthesisUtterance('{$sentence}');
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
            ])
            ->defaultSort('created_at', 'desc');
    }
}
