<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Enums\PostStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Article')
                    ->columnSpan(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(200)
                            ->live(onBlur: true)
                            // Fill the slug from the title until someone edits the slug by hand
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state) {
                                if (($get('slug') ?? '') === Str::slug($old ?? '')) {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(220)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->unique(ignoreRecord: true)
                            ->helperText('Lowercase words separated by hyphens; used in the article URL.'),
                        Textarea::make('excerpt')->rows(2)->maxLength(500),
                        MarkdownEditor::make('body')
                            ->required()
                            ->disableToolbarButtons(['attachFiles']),
                    ]),
                Grid::make(1)
                    ->schema([
                        Section::make('Publishing')
                            ->schema([
                                Select::make('status')->options(PostStatus::class)->required()->default(PostStatus::Draft),
                                DateTimePicker::make('published_at')
                                    ->label('Publish at')
                                    ->helperText('Leave empty to publish now. A future date schedules the post.'),
                            ]),
                        Section::make('Media & SEO')
                            ->schema([
                                FileUpload::make('cover_image_path')
                                    ->label('Cover image')
                                    ->image()
                                    ->disk(config('navesta.media_disk'))
                                    ->directory('blog')
                                    ->visibility('public')
                                    ->maxSize(2048)
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
                                Textarea::make('meta_description')->rows(3)->maxLength(300),
                            ]),
                    ]),
            ]);
    }
}
