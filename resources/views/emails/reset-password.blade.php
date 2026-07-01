<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reinitialisation de mot de passe</title>
</head>
<body>
    <h1>Reinitialisation de mot de passe</h1>
    <p>Bonjour {{ $user->name }},</p>
    <p>Vous avez demande la reinitialisation de votre mot de passe FYA.</p>
    <p>
        <a href="{{ $resetUrl }}">Definir un nouveau mot de passe</a>
    </p>
    <p>Ce lien expire dans {{ $expiresInMinutes }} minutes.</p>
    <p>Si vous n etes pas a l origine de cette demande, ignorez simplement cet email.</p>
</body>
</html>
