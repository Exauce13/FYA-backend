<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ArtisanController;
use App\Http\Controllers\Api\AppeloffreController;
use App\Http\Controllers\Api\CommentairesController;
use App\Http\Controllers\Api\LikesController;
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

Route::middleware('auth:sanctum')->group(function(){
    Route::get('/user', function(Request $request){
        return $request->user();
    });
    Route::prefix('users')->group(function(){
        Route::post('/logout', [UserController::class, 'logout']);
        Route::post('/profile/photo', [UserController::class, 'changementprofile']);
        Route::put('/updateinformation/{user}', [UserController::class, 'updateinfos']);
        Route::post('/appeloffres', [AppeloffreController::class, 'createappeloffre']);
        Route::patch('/closeappel/{id}', [AppeloffreController::class, 'closeappeloffre']);
        Route::patch('/candidatures/{candidature}/accepter', [AppeloffreController::class, 'accepterCandidature']);
        Route::get('/recherche-artisans', [UserController::class, 'rechercheArtisan']);
    });

    Route::prefix('posts')->group(function(){
        Route::post('/{postid}/like', [LikesController::class, 'like']);
        Route::get('/{post}/commentaires', [CommentairesController::class, 'affichercommentaire']);
        Route::post('/commentaires', [CommentairesController::class, 'postercommentaire']);
    });

    Route::prefix('artisans')->group(function(){
        Route::post('/creerposts', [ArtisanController::class, 'createposte']);
        Route::get('/feed-appels-offres', [AppeloffreController::class, 'feedAppelsOffres']);
        Route::post('/appels-offres/{appelOffre}/postuler', [AppeloffreController::class, 'postulerAppelOffre']);
        Route::post('/services', [ServiceController::class, 'creerService']);
    });

    Route::prefix('services')->group(function(){
        Route::get('/{service}', [ServiceController::class, 'voirService']);
        Route::patch('/{service}/valider', [ServiceController::class, 'validerService']);
        Route::patch('/{service}/terminer', [ServiceController::class, 'terminerService']);
    });

    Route::prefix('notifications')->group(function(){
        Route::get('/', [NotificationController::class, 'index']);
        Route::patch('/tout-lire', [NotificationController::class, 'toutLire']);
        Route::patch('/{notification}/lire', [NotificationController::class, 'lire']);
    });

});
