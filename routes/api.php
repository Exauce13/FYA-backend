<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ArtisanController;
use App\Http\Controllers\Api\AppeloffreController;
use App\Http\Controllers\Api\AvisController;
use App\Http\Controllers\Api\CommentairesController;
use App\Http\Controllers\Api\LikesController;
use App\Http\Controllers\Api\MessagerieController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['guest'])->group(function(){
    Route::post('/register', [UserController::class, 'register']);
    Route::post('/login', [UserController::class, 'authenticate']);
    Route::get('/email/verify/{id}/{hash}', [UserController::class, 'verifyEmail'])->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', [UserController::class, 'resendVerificationEmail']);
});

Route::get('/posts/feed', [ArtisanController::class, 'feedPosts']);

Route::middleware('auth:sanctum')->group(function () {


    Route::prefix('users')->group(function () {
        Route::post('/logout', [UserController::class, 'logout']);
        Route::post('/profile/photo', [UserController::class, 'changementprofile']);
        Route::put('/updateinformation/{user}', [UserController::class, 'updateinfos']);
        Route::get('/recherche-artisans', [UserController::class, 'rechercheArtisan']);
        Route::get('/user', [UserController::class, 'profil']);
        Route::get('/profile', [UserController::class, 'profil']);
        Route::get('/users/{user}/profil', [UserController::class, 'profilUtilisateur']);
    });

    Route::prefix('posts')->group(function () {
        Route::post('/creerposts', [ArtisanController::class, 'createposte']);
        Route::post('/{postid}/like', [LikesController::class, 'like']);
        Route::get('/{post}/commentaires', [CommentairesController::class, 'affichercommentaire']);
        Route::post('/commentaires', [CommentairesController::class, 'postercommentaire']);
    });

    Route::prefix('appeloffres')->group(function () {
        Route::post('/appeloffres', [AppeloffreController::class, 'createappeloffre']);
        Route::patch('/closeappel/{id}', [AppeloffreController::class, 'closeappeloffre']);
        Route::patch('/candidatures/{candidature}/accepter', [AppeloffreController::class, 'accepterCandidature']);
        Route::get('/feed-appels-offres', [AppeloffreController::class, 'feedAppelsOffres']);
        Route::post('/appels-offres/{appelOffre}/postuler', [AppeloffreController::class, 'postulerAppelOffre']);

    });

    Route::prefix('services')->group(function () {
        Route::post('/services', [ServiceController::class, 'creerService']);
        Route::get('/{service}', [ServiceController::class, 'voirService']);
        Route::patch('/{service}/valider', [ServiceController::class, 'validerService']);
        Route::patch('/{service}/terminer', [ServiceController::class, 'terminerService']);
        Route::post('/{service}/avis', [ServiceController::class, 'avis']);
        Route::get('/{service}/avis', [AvisController::class, 'serviceAvis']);
    });

    Route::prefix('avis')->group(function () {
        Route::get('/artisans/{artisan}', [AvisController::class, 'artisanAvis']);
        Route::get('/clients/{client}', [AvisController::class, 'clientAvis']);
    });

    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::patch('/tout-lire', [NotificationController::class, 'toutLire']);
        Route::patch('/{notification}/lire', [NotificationController::class, 'lire']);
    });

    Route::prefix('messagerie')->group(function () {
        Route::get('/conversations', [MessagerieController::class, 'conversations']);
        Route::post('/messages/upload', [MessagerieController::class, 'upload']);
        Route::post('/conversations/{conversation}/messages', [MessagerieController::class, 'store']);
        Route::get('/conversations/{conversation}/messages', [MessagerieController::class, 'index']);
    });
});
