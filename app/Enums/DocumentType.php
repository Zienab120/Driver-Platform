<?php

namespace App\Enums;

enum DocumentType: string
{
    // Step 2
    case VehicleFront = 'vehicle_front';
    case VehicleRear = 'vehicle_rear';
    case VehicleInterior = 'vehicle_interior';
    // Step 3
    case NationalIdFront = 'national_id_front';
    case NationalIdBack = 'national_id_back';
    case DrivingLicense = 'driving_license';
    case VehicleLicense = 'vehicle_license';
    case CriminalRecord = 'criminal_record';

    /** @return list<self> */
    public static function vehiclePhotos(): array
    {
        return [self::VehicleFront, self::VehicleRear, self::VehicleInterior];
    }

    /** @return list<self> */
    public static function identityDocuments(): array
    {
        return [self::NationalIdFront, self::NationalIdBack, self::DrivingLicense, self::VehicleLicense, self::CriminalRecord];
    }

    /** @param list<self> $cases @return list<string> */
    public static function values(array $cases): array
    {
        return array_map(fn (self $c) => $c->value, $cases);
    }
}
