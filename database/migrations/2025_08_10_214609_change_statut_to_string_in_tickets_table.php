<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convertit `statut` ENUM(...) => VARCHAR(50) puis normalise les valeurs (fr → en).
     */
    public function up(): void
    {
        // 1) Modifier le type de la colonne pour permettre de nouvelles valeurs (évite l'erreur ENUM)
        DB::statement("
            ALTER TABLE `tickets`
            MODIFY `statut` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open'
        ");

        // 2) Normaliser les valeurs existantes (français → anglais)
        // mapping : 'ouvert' -> 'open', 'en_cours' -> 'in_progress', 'résolu' -> 'closed', 'fermé' -> 'closed'
        DB::statement("
            UPDATE `tickets`
            SET `statut` = CASE
                WHEN `statut` = 'ouvert' THEN 'open'
                WHEN `statut` = 'en_cours' THEN 'in_progress'
                WHEN `statut` = 'résolu' THEN 'closed'
                WHEN `statut` = 'fermé' THEN 'closed'
                ELSE `statut`
            END
        ");
    }

    /**
     * Remet la colonne en ENUM(...) (rétrograde) et convertit les valeurs anglaises → françaises.
     */
    public function down(): void
    {
        // 1) Convertir les valeurs en français compatibles ENUM
        DB::statement("
            UPDATE `tickets`
            SET `statut` = CASE
                WHEN `statut` = 'open' THEN 'ouvert'
                WHEN `statut` = 'in_progress' THEN 'en_cours'
                WHEN `statut` = 'closed' THEN 'résolu'
                ELSE `statut`
            END
        ");

        // 2) Revenir au type ENUM d'origine
        DB::statement("
            ALTER TABLE `tickets`
            MODIFY `statut` ENUM('ouvert','en_cours','résolu','fermé')
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouvert'
        ");
    }
};
