<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\BtApplicationsRelationManager;
use App\Filament\Resources\Users\RelationManagers\ConsentsRelationManager;
use App\Filament\Resources\Users\RelationManagers\CreditCardsRelationManager;
use App\Filament\Resources\Users\RelationManagers\CreditScoresRelationManager;
use App\Filament\Resources\Users\RelationManagers\LoansRelationManager;
use App\Filament\Resources\Users\RelationManagers\StatementsRelationManager;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Customers and admins. Accounts are created through the app (or the
 * navesta:make-admin command), not here, and are not deleted from the
 * panel: deleting a customer erases all their financial records.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Customers';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        // Phone numbers are encrypted at rest, so they cannot be searched
        return ['name', 'email'];
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            CreditCardsRelationManager::class,
            LoansRelationManager::class,
            BtApplicationsRelationManager::class,
            CreditScoresRelationManager::class,
            StatementsRelationManager::class,
            ConsentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
