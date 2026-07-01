<?php

namespace App\Events\Backup;

use App\Models\BackupHistory;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BackupCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public BackupHistory $history,
    ) {}
}
