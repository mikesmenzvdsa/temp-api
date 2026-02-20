<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Review Request</title>
</head>
<body>
    <p>Hi {{ $client_name }},</p>

    <p>We hope you enjoyed your stay at {{ $prop_name }}.</p>

    <p>Please take a moment to leave a review using the link below:</p>

    <p><a href="{{ $url }}">Leave a review</a></p>

    <p>Arrival: {{ $arrival_date }}<br>
    Departure: {{ $departure_date }}</p>

    <p>Thanks,<br>Host Agents</p>
</body>
</html>
