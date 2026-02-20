<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RentalsUnitedService
{
    private string $url = 'https://rm.rentalsunited.com/api/Handler.ashx';
    private string $username = 'book@hostagents.com';
    private string $password = 'TX@m@Yy6hSUs6N!';
    private string $ownerId = '686995';

    public function push(int $propertyId)
    {
        $property = $this->getProperty($propertyId);
        if (!$property) {
            return $this->buildError('Property not found');
        }

        $location = $this->getRuLocation($property);
        $listingType = $this->getListingType($property->listing_type_id);

        $xml = $this->buildPropertyXml($property, $location, $listingType, null);

        return $this->request($xml);
    }

    public function put(int $propertyId, int $rentalsUnitedId)
    {
        $property = $this->getProperty($propertyId);
        if (!$property) {
            return $this->buildError('Property not found');
        }

        $location = $this->getRuLocation($property);
        $listingType = $this->getListingType($property->listing_type_id);

        $xml = $this->buildPropertyXml($property, $location, $listingType, $rentalsUnitedId);

        return $this->request($xml);
    }

    public function getRUProperties(int $propertyId)
    {
        $xml = <<<XML
<GetProperties_RQ>
    <Authentication>
        <UserName>{$this->username}</UserName>
        <Password>{$this->password}</Password>
    </Authentication>
    <OwnerID>{$this->ownerId}</OwnerID>
    <PropertyID>{$propertyId}</PropertyID>
</GetProperties_RQ>
XML;

        return $this->request($xml);
    }

    private function buildPropertyXml(object $property, ?object $location, ?object $listingType, ?int $rentalsUnitedId): string
    {
        $locationTypeId = $location->location_type_id ?? 0;
        $locationId = $location->location_id ?? 0;
        $listingTypeId = $listingType->rentals_united_id ?? 0;
        $propertyTypeId = $property->rentals_united_property_type ?? 0;
        $isActive = (int) ($property->is_live ?? 0);

        $ruIdFragment = $rentalsUnitedId ? "<PropertyID>{$rentalsUnitedId}</PropertyID>" : '';

        $name = $this->xmlEscape($property->name ?? '');
        $address = $this->xmlEscape($property->physical_address ?? '');
        $description = $this->xmlEscape($property->description ?? '');
        $checkin = $property->checkin_time ?? '14:00';
        $checkout = $property->checkout_time ?? '10:00';
        $longitude = $property->longitude ?? '';
        $latitude = $property->latitude ?? '';
        $squareMeters = $property->square_meters ?? 0;
        $capacity = $property->capacity ?? 0;
        $floor = $property->floor ?? '';
        $postalCode = $property->postal_code ?? '';

        $xml = <<<XML
<Push_PutProperty_RQ>
    <Authentication>
        <UserName>{$this->username}</UserName>
        <Password>{$this->password}</Password>
    </Authentication>
    {$ruIdFragment}
    <Property>
        <Name>{$name}</Name>
        <OwnerID>{$this->ownerId}</OwnerID>
        <DetailedLocationID TypeID="{$locationTypeId}">{$locationId}</DetailedLocationID>
        <IsActive>{$isActive}</IsActive>
        <IsArchived>0</IsArchived>
        <Space>{$squareMeters}</Space>
        <StandardGuests>{$capacity}</StandardGuests>
        <CanSleepMax>{$capacity}</CanSleepMax>
        <PropertyTypeID>{$propertyTypeId}</PropertyTypeID>
        <ObjectTypeID>{$listingTypeId}</ObjectTypeID>
        <NoOfUnits>1</NoOfUnits>
        <Floor>{$floor}</Floor>
        <Street>{$address}</Street>
        <ZipCode>{$postalCode}</ZipCode>
        <Coordinates>
            <Longitude>{$longitude}</Longitude>
            <Latitude>{$latitude}</Latitude>
        </Coordinates>
        <ArrivalInstructions>
            <Landlord>Host Agents</Landlord>
            <Email>book@hostagents.com</Email>
            <Phone>+27 87 238 1796</Phone>
            <DaysBeforeArrival>2</DaysBeforeArrival>
            <HowToArrive>
                <Text LanguageID="1">TBD</Text>
            </HowToArrive>
        </ArrivalInstructions>
        <CheckInOut>
            <CheckInFrom>{$checkin}</CheckInFrom>
            <CheckInTo>{$checkin}</CheckInTo>
            <CheckOutUntil>{$checkout}</CheckOutUntil>
            <Place>Apartment</Place>
        </CheckInOut>
        <PaymentMethods>
            <PaymentMethod PaymentMethodID="2">BANK TRANSFER (EFT)</PaymentMethod>
            <PaymentMethod PaymentMethodID="3">CREDIT CARD, DEBIT CARD</PaymentMethod>
        </PaymentMethods>
        <Deposit DepositTypeID="3">100.00</Deposit>
        <CancellationPolicies>
            <CancellationPolicy ValidFrom="0" ValidTo="14">100</CancellationPolicy>
            <CancellationPolicy ValidFrom="15" ValidTo="10000">0</CancellationPolicy>
        </CancellationPolicies>
        <Descriptions>
            <Description LanguageID="1">
                <Text>{$description}</Text>
            </Description>
        </Descriptions>
    </Property>
</Push_PutProperty_RQ>
XML;

        return $xml;
    }

    private function getProperty(int $propertyId): ?object
    {
        return DB::table('virtualdesigns_properties_properties')->where('id', '=', $propertyId)->first();
    }

    private function getListingType($listingTypeId): ?object
    {
        if (!$listingTypeId) {
            return null;
        }

        return DB::table('virtualdesigns_properties_listing_types')->where('id', '=', $listingTypeId)->first();
    }

    private function getRuLocation(object $property): ?object
    {
        $location = null;

        if (!empty($property->suburb_id) && (int) $property->suburb_id !== 138) {
            $location = DB::table('virtualdesigns_rentals_united_locations')
                ->where('vd_location_id', '=', $property->suburb_id)
                ->first();
            if (!$location && !empty($property->city_id)) {
                $location = DB::table('virtualdesigns_rentals_united_locations')
                    ->where('vd_location_id', '=', $property->city_id)
                    ->first();
            }
            if (!$location && !empty($property->country_id)) {
                $location = DB::table('virtualdesigns_rentals_united_locations')
                    ->where('vd_location_id', '=', $property->country_id)
                    ->first();
            }
        } else {
            if (!empty($property->city_id)) {
                $location = DB::table('virtualdesigns_rentals_united_locations')
                    ->where('vd_location_id', '=', $property->city_id)
                    ->first();
            }
            if (!$location && !empty($property->country_id)) {
                $location = DB::table('virtualdesigns_rentals_united_locations')
                    ->where('vd_location_id', '=', $property->country_id)
                    ->first();
            }
        }

        return $location;
    }

    private function request(string $xml)
    {
        $curl = curl_init($this->url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/xml',
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            return $this->buildError($error ?: 'Request failed');
        }

        try {
            return simplexml_load_string($response);
        } catch (\Throwable $th) {
            return $this->buildError('Invalid XML response');
        }
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function buildError(string $message)
    {
        return (object) [
            'Status' => 'Error',
            'Message' => $message,
        ];
    }
}
