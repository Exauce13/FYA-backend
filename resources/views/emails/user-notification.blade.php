<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p>{{ $body }}</p>

    @if (! empty($notification->data_json['description']))
        <p>{{ $notification->data_json['description'] }}</p>
    @endif

    <p>Connectez-vous a FYA pour consulter les details.</p>
</body>
</html>
