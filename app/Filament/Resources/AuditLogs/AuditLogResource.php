<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Read-only view of the audit trail: sign-ins, data changes, admin
 * decisions and downloads.
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Audit log';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')->label('When')->dateTime(),
            TextEntry::make('action')->badge(),
            TextEntry::make('actor.email')->label('Actor')->placeholder(fn (AuditLog $record) => $record->actor_id ? 'deleted account' : $record->actor_role),
            TextEntry::make('actor_role')->label('Role'),
            TextEntry::make('subject_type')->label('Subject')->placeholder('—'),
            TextEntry::make('subject_id')->label('Subject ID')->placeholder('—')->copyable(),
            TextEntry::make('ip_address')->label('IP address')->placeholder('—'),
            TextEntry::make('user_agent')->label('Device')->placeholder('—')->columnSpanFull(),
            KeyValueEntry::make('metadata')->label('Details')->columnSpanFull()
                ->state(fn (AuditLog $record) => collect($record->metadata ?? [])->map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v))->all()),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('actor'))
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('action')->badge()->searchable(),
                TextColumn::make('actor.email')->label('Actor')->placeholder('—')->searchable(),
                TextColumn::make('actor_role')->label('Role'),
                TextColumn::make('subject_type')->label('Subject')->placeholder('—'),
                TextColumn::make('ip_address')->label('IP')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('actor_role')->options(['customer' => 'Customer', 'admin' => 'Admin', 'system' => 'System', 'guest' => 'Guest']),
                SelectFilter::make('action')->options(fn () => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
            ])
            ->headerActions([
                \Filament\Actions\Action::make('export_csv')
                    ->label('Export Audit Log')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(function () {
                        return response()->streamDownload(function () {
                            $handle = fopen('php://output', 'w');
                            fputcsv($handle, ['ID', 'Timestamp', 'Actor Role', 'Action', 'Subject Type', 'Subject ID', 'IP Address', 'User Agent']);
                            AuditLog::query()->orderBy('created_at', 'desc')->chunk(500, function ($logs) use ($handle) {
                                foreach ($logs as $log) {
                                    fputcsv($handle, [
                                        $log->id,
                                        $log->created_at?->toIso8601String(),
                                        $log->actor_role,
                                        $log->action,
                                        $log->subject_type ?? '',
                                        $log->subject_id ?? '',
                                        $log->ip_address ?? '',
                                        $log->user_agent ?? '',
                                    ]);
                                }
                            });
                            fclose($handle);
                        }, 'audit-logs-' . date('Y-m-d') . '.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }
}
