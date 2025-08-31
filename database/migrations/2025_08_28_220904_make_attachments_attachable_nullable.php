<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        // MySQL raw ALTER (compatible sans doctrine/dbal)
        DB::statement("
            ALTER TABLE `attachments`
            MODIFY `attachable_type` VARCHAR(255) NULL,
            MODIFY `attachable_id` BIGINT UNSIGNED NULL
        ");
    }

    public function down()
    {
        DB::statement("
            ALTER TABLE `attachments`
            MODIFY `attachable_type` VARCHAR(255) NOT NULL,
            MODIFY `attachable_id` BIGINT UNSIGNED NOT NULL
        ");
    }
};
