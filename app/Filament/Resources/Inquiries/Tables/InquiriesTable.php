<?php

namespace App\Filament\Resources\Inquiries\Tables;

use App\Enums\InquiryStatus;
use App\Filament\Resources\Inquiries\StatusActions;
use App\Models\Inquiry;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InquiriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Received')->dateTime()->since()->sortable(),
                TextColumn::make('inquiry_type')->label('Type')->badge()->color('gray')->searchable(),
                TextColumn::make('name')->placeholder('—')->searchable(),
                TextColumn::make('phone')->placeholder('—')->copyable()->searchable(),
                TextColumn::make('email')->placeholder('—')->copyable()->searchable(),
                TextColumn::make('message')->limit(60)->tooltip(fn (Inquiry $record) => $record->message)->wrap(),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(InquiryStatus::class),
                SelectFilter::make('inquiry_type')
                    ->label('Type')
                    ->options(fn () => Inquiry::query()->distinct()->orderBy('inquiry_type')->pluck('inquiry_type', 'inquiry_type')->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make(StatusActions::make()),
            ])
            ->emptyStateHeading('No leads yet')
            ->emptyStateDescription('Messages from the Contact page and personal-loan callback requests from the home page appear here.');
    }
}
