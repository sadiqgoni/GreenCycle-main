<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AdminServiceRequestResource\Pages;
use App\Filament\Admin\Resources\AdminServiceRequestResource\RelationManagers;
use App\Models\Company;
use App\Models\ServiceRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Payment;
use Filament\Notifications\Notification;

class AdminServiceRequestResource extends Resource
{
    protected static ?string $model = ServiceRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $modelLabel = 'Service Request';

    protected static ?string $navigationLabel = 'Service Request';
    protected static ?string $navigationGroup = 'Request Management';
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')
            ->count();
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('waste_type')
                    ->weight('bold')
                    ->color(fn(?Model $record): array => \Filament\Support\Colors\Color::hex(optional($record->tenant)->color ?? '#22e03a'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('household.name')
                    ->searchable()
                    ->label('Client'),
                    Tables\Columns\TextColumn::make('address')
                    ->searchable()
                    ->label('Address'),
                Tables\Columns\TextColumn::make('client_number')
                    ->searchable()
                    ->label('Phone'),
                    Tables\Columns\TextColumn::make('accepted_company_id')
                    ->formatStateUsing(fn($state): string => Company::find((int) $state)?->company_name ?? 'Pending Company') // Cast to int if needed
                    ->label('Assigned Company')
                    ->badge()
                    ->default('pending')
                    ->colors([
                        'success' => 'confirmed',
                        'warning' => 'pending',
                        'danger' => 'failed',
                    ]),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'assigned' => 'warning',
                        'accepted' => 'success',
                        'awaiting_payment' => 'warning',
                        'payment_sent' => 'info',
                        'in_progress' => 'info',
                        'completed' => 'success',
                        'payment_released' => 'success',

                        'paid' => 'success',
                        'cancelled' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('final_amount')
                    ->money('NGN'),
                Tables\Columns\BadgeColumn::make('payment.status')
                    ->label('Payment Status')
                    ->badge()
                    ->default('pending')
                    ->colors([
                        'success' => 'confirmed',
                        'warning' => 'pending',
                        'danger' => 'failed',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'assigned' => 'Assigned',
                        'accepted' => 'Accepted',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'paid' => 'Paid',
                        'payment_released' => 'Payment Released',
                        'cancelled' => 'Cancelled',
                    ]),

            ])
            ->actions([
                Tables\Actions\Action::make('release_payment')
                    ->visible(fn($record) => 
                        $record->status === 'completed' && 
                        $record->payment && 
                        $record->payment->status === 'confirmed' && 
                        !$record->commission_paid_at
                    )
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->form([
                        Forms\Components\Placeholder::make('completion_images')
                            ->label('Service Completion Images')
                            ->content(function ($record) {
                                $html = '<div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">';
                                
                                // Company uploaded images
                                if ($record->completion_photos) {
                                    $photos = is_array($record->completion_photos) 
                                        ? $record->completion_photos 
                                        : json_decode($record->completion_photos);
                                    if (is_array($photos)) {
                                        foreach ($photos as $photo) {
                                            $html .= '<div style="margin: 5px;"><p style="margin: 0; font-size: 12px;">Company Photo:</p>';
                                            $html .= '<img src="' . asset('storage/' . $photo) . '" style="width: 150px; height: 150px; object-fit: cover; border-radius: 8px;">';
                                            $html .= '</div>';
                                        }
                                    }
                                }
                                
                                // Household uploaded images
                                if ($record->household_completion_photos) {
                                    $photos = is_array($record->household_completion_photos) 
                                        ? $record->household_completion_photos 
                                        : json_decode($record->household_completion_photos);
                                    if (is_array($photos)) {
                                        foreach ($photos as $photo) {
                                            $html .= '<div style="margin: 5px;"><p style="margin: 0; font-size: 12px;">Household Photo:</p>';
                                            $html .= '<img src="' . asset('storage/' . $photo) . '" style="width: 150px; height: 150px; object-fit: cover; border-radius: 8px;">';
                                            $html .= '</div>';
                                        }
                                    }
                                }
                                
                                $html .= '</div>';
                                return new \Illuminate\Support\HtmlString($html);
                            }),
                        Forms\Components\Placeholder::make('completion_notes')
                            ->label('Completion Notes')
                            ->content(function ($record) {
                                $html = '<div style="margin-bottom: 20px;">';
                                if ($record->completion_notes) {
                                    $html .= '<p><strong>Company Notes:</strong><br>' . nl2br(e($record->completion_notes)) . '</p>';
                                }
                                if ($record->household_completion_notes) {
                                    $html .= '<p><strong>Household Notes:</strong><br>' . nl2br(e($record->household_completion_notes)) . '</p>';
                                }
                                $html .= '</div>';
                                return new \Illuminate\Support\HtmlString($html);
                            }),
                        Forms\Components\TextInput::make('commission_percentage')
                            ->label('Commission Percentage (%)')
                            ->default(10)
                            ->numeric()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, ServiceRequest $record) {
                                $payment = $record->payment;
                                if ($payment) {
                                    $amount = $payment->amount;
                                    $commission = $amount * ($state / 100);
                                    $companyAmount = $amount - $commission;
                                    $set('commission_amount', $commission);
                                    $set('company_amount', $companyAmount);
                                }
                            }),
                        Forms\Components\Placeholder::make('payment_amount')
                            ->label('Total Payment Amount')
                            ->content(function (ServiceRequest $record) {
                                return '₦' . number_format($record->payment?->amount ?? 0, 2);
                            }),
                        Forms\Components\Placeholder::make('commission_amount')
                            ->label('Commission Amount')
                            ->content(fn($get) => '₦' . number_format($get('commission_amount') ?? 0, 2)),
                        Forms\Components\Placeholder::make('company_amount')
                            ->label('Amount to Release to Company')
                            ->content(fn($get) => '₦' . number_format($get('company_amount') ?? 0, 2)),
                    ])
                    ->action(function (ServiceRequest $record, array $data): void {
                        $payment = $record->payment;
                        if (!$payment) {
                            Notification::make()
                                ->title('Error')
                                ->body('No payment record found.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $commissionPercentage = $data['commission_percentage'];
                        $commissionAmount = $payment->amount * ($commissionPercentage / 100);
                        $companyAmount = $payment->amount - $commissionAmount;

                        $payment->update([
                            'commission_amount' => $commissionAmount,
                            'company_amount' => $companyAmount,
                        ]);

                        $record->update([
                            'admin_commission_percentage' => $commissionPercentage,
                            'admin_commission_amount' => $commissionAmount,
                            'company_payout_amount' => $companyAmount,
                            'commission_paid_at' => now(),
                            'status' => 'payment_released'
                        ]);

                        Notification::make()
                            ->title('Payment Released')
                            ->body("Payment of ₦" . number_format($companyAmount, 2) . " has been released to the company.")
                            ->success()
                            ->send();
                    })
                    ->mutateFormDataUsing(function (array $data, ServiceRequest $record): array {
                        // Set initial values when form opens
                        $payment = $record->payment;
                        if ($payment) {
                            $commissionPercentage = $data['commission_percentage'] ?? 10;
                            $commissionAmount = $payment->amount * ($commissionPercentage / 100);
                            $companyAmount = $payment->amount - $commissionAmount;
                            
                            $data['commission_amount'] = $commissionAmount;
                            $data['company_amount'] = $companyAmount;
                        }
                        return $data;
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceRequests::route('/'),
            'create' => Pages\CreateServiceRequest::route('/create'),
            'edit' => Pages\EditServiceRequest::route('/{record}/edit'),
        ];
    }
}