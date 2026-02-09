<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Welcome Letter</title>
</head>
<body>
    <h2>Welcome to {{ $prop_name }}</h2>

    <p>Hi {{ $client_name }},</p>

    <p>We are looking forward to hosting you. Here are your stay details:</p>

    <ul>
        <li>Arrival: {{ $arrival_date }}</li>
        <li>Departure: {{ $departure_date }}</li>
        <li>Booking reference: {{ $booking_ref }}</li>
    </ul>

    <h3>Property details</h3>
    <p>{{ $physical_address }}</p>
    @if (!empty($directions_link))
        <p>Directions: <a href="{{ $directions_link }}">{{ $directions_link }}</a></p>
    @endif

    @if (!empty($prop_photo))
        <p><img src="{{ $prop_photo }}" alt="Property photo" style="max-width: 100%; height: auto;"></p>
    @endif

    <h3>Check-in information</h3>
    @if (!empty($guest_checkin_info))
        <p>{{ $guest_checkin_info }}</p>
    @endif

    @if (!empty($parking_notes))
        <p><strong>Parking:</strong> {{ $parking_notes }}</p>
    @endif

    @if (!empty($wifi_username) || !empty($wifi_password))
        <p><strong>WiFi:</strong> {{ $wifi_username }} / {{ $wifi_password }}</p>
    @endif

    @if (!empty($tv_instructions))
        <p><strong>TV:</strong> {{ $tv_instructions }}</p>
    @endif

    @if (!empty($meter_number))
        <p><strong>Meter number:</strong> {{ $meter_number }}</p>
    @endif

    @if (!empty($refuse_collection_notes))
        <p><strong>Refuse collection:</strong> {{ $refuse_collection_notes }}</p>
    @endif

    @if (!empty($guest_info))
        <p><strong>Guest info:</strong> {{ $guest_info }}</p>
    @endif

    @if (!empty($guest_departure_info))
        <p><strong>Departure info:</strong> {{ $guest_departure_info }}</p>
    @endif

    @if (!empty($inv_url))
        <p>Inventory: <a href="{{ $inv_url }}">{{ $inv_url }}</a></p>
    @endif

    <p>If you have questions, contact {{ $prop_manager_name }} at {{ $prop_manager_phone }}.</p>

    <p>Thanks,<br>Host Agents</p>
</body>
</html>
