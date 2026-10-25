<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount(['creditCards', 'loans', 'btApplications']))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('phone')->placeholder('—'),
                TextColumn::make('role')->badge(),
                IconColumn::make('is_verified')->label('Verified')->boolean(),
                TextColumn::make('credit_cards_count')->label('Cards')->sortable(),
                TextColumn::make('loans_count')->label('Loans')->sortable(),
                TextColumn::make('bt_applications_count')->label('BT apps')->sortable(),
                TextColumn::make('created_at')->label('Joined')->dateTime()->sortable(),
                TextColumn::make('deleted_at')->label('Deleted')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')->options(UserRole::class),
                TernaryFilter::make('is_verified')->label('Verified'),
                TrashedFilter::make(),
            ])
            ->headerActions([
                Action::make('export_csv')
                    ->label('Export CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(function () {
                        return response()->streamDownload(function () {
                            $handle = fopen('php://output', 'w');
                            fputcsv($handle, ['ID', 'Name', 'Email', 'Role', 'Verified', 'Created At', 'Deleted At']);
                            User::withTrashed()->chunk(250, function ($users) use ($handle) {
                                foreach ($users as $user) {
                                    fputcsv($handle, [
                                        $user->id,
                                        $user->name,
                                        $user->email,
                                        $user->role?->value ?? (string) $user->role,
                                        $user->is_verified ? 'Yes' : 'No',
                                        $user->created_at?->toIso8601String(),
                                        $user->deleted_at?->toIso8601String() ?? '',
                                    ]);
                                }
                            });
                            fclose($handle);
                        }, 'users-export-' . date('Y-m-d') . '.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                RestoreAction::make(),
            ]);
    }
}
