<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('countries')->insert([
            ['nom' => 'Bénin', 'code_pays' => 'BEN', 'code_indicatif' => '+229', 'tarif_sms' => 0.0250, 'statut' => true],
            ['nom' => 'Burkina Faso', 'code_pays' => 'BFA', 'code_indicatif' => '+226', 'tarif_sms' => 0.0280, 'statut' => true],
            ['nom' => 'Côte d\'Ivoire', 'code_pays' => 'CIV', 'code_indicatif' => '+225', 'tarif_sms' => 0.0270, 'statut' => true],
            ['nom' => 'Ghana', 'code_pays' => 'GHA', 'code_indicatif' => '+233', 'tarif_sms' => 0.0300, 'statut' => true],
            ['nom' => 'Guinée', 'code_pays' => 'GIN', 'code_indicatif' => '+224', 'tarif_sms' => 0.0290, 'statut' => true],
            ['nom' => 'Guinée-Bissau', 'code_pays' => 'GNB', 'code_indicatif' => '+245', 'tarif_sms' => 0.0310, 'statut' => true],
            ['nom' => 'Mali', 'code_pays' => 'MLI', 'code_indicatif' => '+223', 'tarif_sms' => 0.0280, 'statut' => true],
            ['nom' => 'Niger', 'code_pays' => 'NER', 'code_indicatif' => '+227', 'tarif_sms' => 0.0270, 'statut' => true],
            ['nom' => 'Nigéria', 'code_pays' => 'NGA', 'code_indicatif' => '+234', 'tarif_sms' => 0.0350, 'statut' => true],
            ['nom' => 'Sénégal', 'code_pays' => 'SEN', 'code_indicatif' => '+221', 'tarif_sms' => 0.0260, 'statut' => true],
            ['nom' => 'Sierra Leone', 'code_pays' => 'SLE', 'code_indicatif' => '+232', 'tarif_sms' => 0.0320, 'statut' => true],
            ['nom' => 'Togo', 'code_pays' => 'TGO', 'code_indicatif' => '+228', 'tarif_sms' => 0.0250, 'statut' => true],
            ['nom' => 'France', 'code_pays' => 'FRA', 'code_indicatif' => '+33', 'tarif_sms' => 0.0450, 'statut' => true],
            ['nom' => 'Congo Brazzaville', 'code_pays' => 'COG', 'code_indicatif' => '+242', 'tarif_sms' => 0.0300, 'statut' => true],
            ['nom' => 'RDC', 'code_pays' => 'COD', 'code_indicatif' => '+243', 'tarif_sms' => 0.0310, 'statut' => true],
            ['nom' => 'Tchad', 'code_pays' => 'TCD', 'code_indicatif' => '+235', 'tarif_sms' => 0.0290, 'statut' => true],
            ['nom' => 'Gabon', 'code_pays' => 'GAB', 'code_indicatif' => '+241', 'tarif_sms' => 0.0300, 'statut' => true],
            ['nom' => 'Kenya', 'code_pays' => 'KEN', 'code_indicatif' => '+254', 'tarif_sms' => 0.0330, 'statut' => true],
            ['nom' => 'Zambie', 'code_pays' => 'ZMB', 'code_indicatif' => '+260', 'tarif_sms' => 0.0320, 'statut' => true],
            ['nom' => 'Guinée Conakry', 'code_pays' => 'GNC', 'code_indicatif' => '+224', 'tarif_sms' => 0.0290, 'statut' => true],
            ['nom' => 'Maroc', 'code_pays' => 'MAR', 'code_indicatif' => '+212', 'tarif_sms' => 0.0380, 'statut' => true],
            ['nom' => 'Égypte', 'code_pays' => 'EGY', 'code_indicatif' => '+20', 'tarif_sms' => 0.0370, 'statut' => true],
        ]);
    }
}
