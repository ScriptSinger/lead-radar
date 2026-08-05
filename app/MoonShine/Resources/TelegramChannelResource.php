<?php
declare(strict_types=1);
namespace App\MoonShine\Resources;
use App\Jobs\ScanTelegramChannelJob; use App\Models\TelegramChannel; use MoonShine\Laravel\Resources\ModelResource; use MoonShine\Support\Attributes\AsyncMethod; use MoonShine\UI\Fields\{Date,ID,Switcher,Text,Url};
/** @extends ModelResource<TelegramChannel> */
class TelegramChannelResource extends ModelResource {
 protected string $model=TelegramChannel::class; protected string $title='Telegram Channels'; protected string $column='name';
 protected function indexFields(): iterable{return[ID::make()->sortable(),Text::make('Name','name')->sortable(),Text::make('Username','username'),Url::make('URL','url')->blank(),Switcher::make('Active','active'),Date::make('Last scan','last_scan_at')->format('Y-m-d H:i')];}
 protected function formFields(): iterable{return[Text::make('Name','name')->required(),Url::make('Public URL','url')->required(),Switcher::make('Active','active')];}
 protected function detailFields(): iterable{return[ID::make(),Text::make('Name','name'),Text::make('Username','username'),Url::make('URL','url')->blank(),Switcher::make('Active','active'),Text::make('Telegram ID','telegram_channel_id'),Date::make('Last scan','last_scan_at')->format('Y-m-d H:i:s')];}
 #[AsyncMethod] public function scanNow(): void {$c=TelegramChannel::query()->find((int)request('resourceItem'));if($c?->active){ScanTelegramChannelJob::dispatch($c->id,(int)config('services.telegram.scan.limit',20),'admin');toast('Telegram scan queued');}}
}
