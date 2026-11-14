<?php

namespace App\Filament\Resources\Inquiries\Schemas;

use App\Models\Inquiry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InquiryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Lead')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')->badge(),
                        TextEntry::make('inquiry_type')->label('Type'),
                        TextEntry::make('created_at')->label('Received')->dateTime()->since(),
                        TextEntry::make('name')->placeholder('Not given'),
                        TextEntry::make('phone')
                            ->placeholder('Not given')
                            ->copyable()
                            ->url(fn (Inquiry $record) => $record->phone ? 'tel:'.preg_replace('/[^0-9+]/', '', $record->phone) : null),
                        TextEntry::make('email')
                            ->placeholder('Not given')
                            ->copyable()
                            ->url(fn (Inquiry $record) => $record->email ? 'mailto:'.$record->email : null),
                    ]),
                Section::make('Message')
                    ->schema([
                        TextEntry::make('message')->hiddenLabel()->columnSpanFull(),
                    ]),
                Section::make('Record')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('ip_address')->label('IP address')->placeholder('—'),
                        TextEntry::make('updated_at')->label('Last change')->dateTime(),
                    ]),
            ]);
    }
}
