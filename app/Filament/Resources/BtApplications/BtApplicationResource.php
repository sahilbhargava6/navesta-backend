<?php

namespace App\Filament\Resources\BtApplications;

use App\Enums\BtStatus;
use App\Filament\Resources\BtApplications\Pages\ListBtApplications;
use App\Filament\Resources\BtApplications\Pages\ViewBtApplication;
use App\Filament\Resources\BtApplications\Schemas\BtApplicationInfolist;
use App\Filament\Resources\BtApplications\Tables\BtApplicationsTable;
use App\Models\BtApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Balance-transfer applications submitted by customers. Admins review them
 * (approve / reject / disburse) but never create or edit their figures.
 */
class BtApplicationResource extends Resource
{
    protected static ?string $model = BtApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Lending';

    protected static ?string $navigationLabel = 'Balance transfers';

    protected static ?string $modelLabel = 'balance transfer application';

    public static function getNavigationBadge(): ?string
    {
        $pending = BtApplication::where('status', BtStatus::Submitted)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return BtApplicationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BtApplicationsTable::configure($table);
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
            'index' => ListBtApplications::route('/'),
            'view' => ViewBtApplication::route('/{record}'),
        ];
    }
}
