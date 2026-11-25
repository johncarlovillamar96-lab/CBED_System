<?php

namespace App\Support;

final class ActivityFields
{
    // Fields are explicitly allowlisted before data leaves the source row.
    public const TABLES = [
        'users' => ['Accounts', 'name role'],
        'cooperatives' => ['Cooperatives', 'cooperative_name cooperative_code accreditation_status coop_type category date_established year_enrolled province municipality barangay region'],
        'farmers' => ['Farmers', 'cooperative_id farmer_code first_name middle_name last_name acquisition_date'],
        'carabaos' => ['Carabaos', 'cooperative_id farmer_id animal_id registry_number sex breed classification ownership_status entrustment status birth_date reproductive_status lactation_status bolus_id tag_number'],
        'calves' => ['Calf Drop', 'calf_animal_id dam_id sire_id birth_date sex birth_weight monitoring_date birth_outcome animal_status'],
        'mating_records' => ['Mating and Breeding', 'cooperative_id carabao_id registry_number mating_date mating_type bull_id semen_bull_id pregnancy_check_date pregnancy_result expected_calving_date actual_calving_date'],
        'health_records' => ['Health and Nutrition', 'cooperative_id carabao_id registry_number monitoring_date health_status record_category'],
        'bcs_records' => ['Body Condition Score', 'cooperative_id carabao_id registry_number record_date body_condition_score monitoring_report_detail_id health_record_id'],
        'milk_records' => ['Milk Production', 'cooperative_id carabao_id registry_number record_date collection_period number_of_cows total_liters price_per_kg milk_traded'],
        'mtd_records' => ['Milk Test', 'cooperative_id carabao_id registry_number test_date dryoff_date test_day mtd_value right_front right_back left_front left_back right_front_result right_back_result left_front_result left_back_result bulk_kg bulk_result'],
        'nature_of_exits' => ['Nature of Exit', 'cooperative_id carabao_id registry_number exit_date nature_of_exit ownership_status'],
        'fhpr_records' => ['Female Herd Performance', 'cooperative_id carabao_id registry_number dry_off_date pregnancy_status days_in_milk animal_type present_calving_date'],
        'semen_bulls' => ['Semen Bulls', 'bull_id bull_name breed status semen_used'],
        'semen_usage' => ['Semen Usage', 'semen_bull_id carabao_id used_date quantity_used'],
        'cooperative_product_records' => ['Cooperative Products', 'cooperative_id product_type actual_date reported_date volume_kg other_source_volume_kg price_per_kg value_php'],
        'monitoring_reports' => ['Animal Monitoring', 'cooperative_id cooperative_name report_date'],
        'monitoring_report_details' => ['Animal Monitoring', 'monitoring_report_id carabao_id animal_database_id registry_number availability_status unavailability_reason monitoring_specification destination_module destination_record_id destination_inventory_id monitoring_status body_condition_score'],
        'cooperative_workbook_import_sheets' => ['Workbook Import', 'workbook_import_id target_module status rows_seen rows_imported rows_inserted rows_updated rows_skipped rows_rejected rows_unmapped'],
        'cooperative_workbook_imports' => ['Workbook Import', 'cooperative_id status'],
    ];

    // Record that these fields changed without retaining either value.
    public const PRIVATE_FIELDS = [
        'users' => ['email', 'password'],
        'farmers' => ['barangay', 'municipality', 'province', 'district', 'region', 'acquisition_cost'],
        'health_records' => ['findings', 'treatment', 'remarks'],
        'monitoring_reports' => ['remarks', 'monitored_by', 'attested_by', 'noted_by'],
        'monitoring_report_details' => ['remarks'],
    ];
}
