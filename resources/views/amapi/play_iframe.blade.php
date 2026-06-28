@extends('layouts.admin')

@section('content')
<div class="container">
    <h2>Ajouter une Application Privée (APK) via Managed Google Play</h2>

    <div id="managed-play-container" style="width: 100%; height: 800px; border: none;"></div>
</div>

<script src="https://play.google.com/work/embedded/search/sdk.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Initialiser l'iFrame dans le conteneur HTML
        gplay.search.init({
            token: "{{ $token }}",
            containerId: "managed-play-container",
            // Déclencheurs et styles optionnels
            options: {
                title: "Applications d'entreprise",
                clp: true // Permet l'affichage de l'onglet de publication d'applications privées
            },
            onSignOut: function() {
                console.log("Déconnexion de l'utilisateur de l'iFrame");
            }
        });
    });
</script>
@endsection
