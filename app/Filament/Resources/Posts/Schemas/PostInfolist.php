<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Models\Post;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class PostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')->badge(),
                        TextEntry::make('published_at')->dateTime()->placeholder('Not published'),
                        TextEntry::make('author.name')->label('Author')->placeholder('—'),
                        TextEntry::make('slug'),
                        TextEntry::make('excerpt')->columnSpan(2)->placeholder('—'),
                        ImageEntry::make('cover_image_path')->label('Cover image')->disk(config('navesta.media_disk'))->placeholder('None'),
                    ]),
                Section::make('Preview')
                    ->schema([
                        // Same sanitised HTML the public API serves
                        TextEntry::make('body')
                            ->hiddenLabel()
                            ->formatStateUsing(fn (Post $record) => new HtmlString($record->bodyHtml()))
                            ->prose(),
                    ]),
            ]);
    }
}
