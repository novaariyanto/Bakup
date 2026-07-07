<?php

namespace App\Events\MyDumper;

use App\Models\MyDumperExport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExportVerificationFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public MyDumperExport $export,
        public string $reason,
    ) {}
}
