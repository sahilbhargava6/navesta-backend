<?php

namespace App\Filament\Resources\Lenders;

use App\Filament\Resources\Lenders\Pages\CreateLender;
use App\Filament\Resources\Lenders\Pages\EditLender;
use App\Filament\Resources\Lenders\Pages\ListLenders;
use App\Filament\Resources\Lenders\Schemas\LenderForm;
use App\Filament\Resources\Lenders\Tables\LendersTable;
use App\Models\Lender;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class LenderResource extends Resource
{
    protected static ?string $model = Lender::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Lending';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return LenderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LendersTable::configure($table);
    }

    /**
     * Lenders are deactivated, never deleted: applications reference them.
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLenders::route('/'),
            'create' => CreateLender::route('/create'),
            'edit' => EditLender::route('/{record}/edit'),
        ];
    }
}
