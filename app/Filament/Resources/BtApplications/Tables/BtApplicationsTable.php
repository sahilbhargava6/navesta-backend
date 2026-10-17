<?php

namespace App\Filament\Resources\BtApplications\Tables;

use App\Enums\BtStatus;
use App\Filament\Resources\BtApplications\ReviewActions;
use App\Filament\Support\Display;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BtApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'lender']))
            ->columns([
                TextColumn::make('created_at')->label('Submitted')->dateTime()->sortable(),
                TextColumn::make('user.name')->label('Customer')->searchable(),
                TextColumn::make('lender.name')->label('Lender')->searchable(),
                TextColumn::make('outstanding_paise')->label('Amount')->formatStateUsing(fn (int $state) => Display::rupees($state))->sortable(),
                TextColumn::make('current_rate_bps')->label('From')->formatStateUsing(fn (int $state) => Display::percent($state)),
                TextColumn::make('new_rate_bps')->label('To')->formatStateUsing(fn (?int $state) => Display::percent($state)),
                TextColumn::make('crif_score')->label('Score')->placeholder('—'),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options(BtStatus::class)->default(BtStatus::Submitted->value),
                SelectFilter::make('lender')->relationship('lender', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make(ReviewActions::make()),
            ]);
    }
}
