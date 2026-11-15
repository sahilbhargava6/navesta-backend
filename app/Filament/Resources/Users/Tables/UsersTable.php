<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\JourneyCatalog;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount(['creditCards', 'loans', 'btApplications'])->with('latestJourneyEvent'))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('phone')->placeholder('—'),
                TextColumn::make('role')->badge(),
                IconColumn::make('is_verified')->label('Verified')->boolean(),
                // The customer's latest journey step: the step, then "journey · when" under it
                TextColumn::make('latestJourneyEvent.step')
                    ->label('Last step')
                    ->formatStateUsing(fn ($state, User $record) => JourneyCatalog::stepText($record->latestJourneyEvent))
                    ->description(fn (User $record) => $record->latestJourneyEvent
                        ? JourneyCatalog::journeyLabel($record->latestJourneyEvent->journey).' · '.$record->latestJourneyEvent->created_at->diffForHumans()
                        : null)
                    ->placeholder('—'),
                TextColumn::make('credit_cards_count')->label('Cards')->sortable(),
                TextColumn::make('loans_count')->label('Loans')->sortable(),
                TextColumn::make('bt_applications_count')->label('BT apps')->sortable(),
                TextColumn::make('created_at')->label('Joined')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->options(UserRole::class),
                TernaryFilter::make('is_verified')->label('Verified'),
                // Furthest step reached in a journey, whatever they did afterwards elsewhere:
                // "Credit card · Offers shown" = saw card offers and never applied
                SelectFilter::make('stopped_at')
                    ->label('Stopped at (furthest step)')
                    ->options(JourneyCatalog::mainStepOptions())
                    ->query(function (Builder $query, array $data) {
                        if (! filled($data['value'] ?? null) || ! str_contains($data['value'], '.')) {
                            return $query;
                        }
                        [$journey, $step] = explode('.', $data['value'], 2);
                        $later = JourneyCatalog::stepsAfter($journey, $step);

                        return $query
                            ->whereHas('journeyEvents', fn (Builder $q) => $q->where('journey', $journey)->where('step', $step))
                            ->when($later !== [], fn (Builder $q) => $q->whereDoesntHave(
                                'journeyEvents',
                                fn (Builder $e) => $e->where('journey', $journey)->whereIn('step', $later),
                            ));
                    }),
            ])
            ->headerActions([
                Action::make('export_csv')
                    ->label('Export CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(function () {
                        return response()->streamDownload(function () {
                            $handle = fopen('php://output', 'w');
                            fputcsv($handle, ['ID', 'Name', 'Email', 'Role', 'Verified', 'Created At']);
                            User::query()->chunk(250, function ($users) use ($handle) {
                                foreach ($users as $user) {
                                    fputcsv($handle, [
                                        $user->id,
                                        $user->name,
                                        $user->email,
                                        $user->role->value,
                                        $user->is_verified ? 'Yes' : 'No',
                                        $user->created_at?->toIso8601String(),
                                    ]);
                                }
                            });
                            fclose($handle);
                        }, 'users-export-'.date('Y-m-d').'.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
