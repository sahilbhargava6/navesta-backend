<?php

namespace App\Filament\Widgets;

use App\Enums\InquiryStatus;
use App\Filament\Resources\Inquiries\InquiryResource;
use App\Models\Inquiry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Dashboard: the newest leads still waiting for a call back or reply.
 */
class LatestLeads extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('New leads & messages')
            ->description('Waiting for a call back or reply, newest first')
            ->query(Inquiry::query()->where('status', InquiryStatus::Pending)->latest()->limit(8))
            ->paginated(false)
            ->columns([
                TextColumn::make('created_at')->label('Received')->since(),
                TextColumn::make('inquiry_type')->label('Type')->badge()->color('gray'),
                TextColumn::make('name')->placeholder('—'),
                TextColumn::make('phone')->placeholder('—')->copyable(),
                TextColumn::make('email')->placeholder('—'),
                TextColumn::make('message')->limit(50),
            ])
            ->recordUrl(fn (Inquiry $record) => InquiryResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('No new leads')
            ->emptyStateDescription('Contact-page messages and loan callback requests will show up here.');
    }
}
