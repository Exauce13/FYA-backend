<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminNotificationController;
use App\Http\Controllers\Api\AdminOfferController;
use App\Http\Controllers\Api\AdminPaymentController;
use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AdminVerificationController;
use App\Http\Controllers\Api\ArtisanController;
use App\Http\Controllers\Api\AppeloffreController;
use App\Http\Controllers\Api\AvisController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CommentairesController;
use App\Http\Controllers\Api\MetierController;
use App\Http\Controllers\Api\LikesController;
use App\Http\Controllers\Api\MessagerieController;
use App\Http\Controllers\Api\PlainteController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['guest'])->group(function(){
    Route::post('/register', [UserController::class, 'register']);
    Route::post('/register/client', [UserController::class, 'register'])->defaults('statut', 'clients');
    Route::post('/register/artisan', [UserController::class, 'register'])->defaults('statut', 'artisans');
    Route::post('/login', [UserController::class, 'authenticate']);
    Route::get('/email/verify/{id}/{hash}', [UserController::class, 'verifyEmail'])->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', [UserController::class, 'resendVerificationEmail']);
    // Route utilisée pour alimenter les listes de métiers dans les formulaires (id + nom).
    Route::get('/metiers', [MetierController::class, 'listesmetiers']);
});

Route::get('/posts/feed', [ArtisanController::class, 'feedPosts']);
Route::get('/artisans/{artisan}/posts', [ArtisanController::class, 'artisanPosts']);
Route::get('/artisans/{artisan}/avis', [ArtisanController::class, 'artisanAvis']);
Route::match(['get', 'post'], '/fedapay/certification/{reference}/callback', [ArtisanController::class, 'fedapayCertificationCallback'])->name('fedapay.certification.callback');

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/overview', [AdminDashboardController::class, 'overview']);

        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::patch('/users/{user}/suspend', [AdminUserController::class, 'suspend']);
        Route::patch('/users/{user}/activate', [AdminUserController::class, 'activate']);

        Route::get('/verifications', [AdminVerificationController::class, 'index']);
        Route::get('/verifications/{artisan}', [AdminVerificationController::class, 'show']);
        Route::patch('/verifications/{artisan}/validate', [AdminVerificationController::class, 'validateVerification']);
        Route::patch('/verifications/{artisan}/cancel', [AdminVerificationController::class, 'cancelVerification']);
        Route::get('/verifications/{artisan}/documents/{document}/download', [AdminVerificationController::class, 'downloadDocument']);

        Route::get('/offers', [AdminOfferController::class, 'index']);
        Route::get('/offers/{appelOffre}', [AdminOfferController::class, 'show']);
        Route::delete('/offers/{appelOffre}', [AdminOfferController::class, 'destroy']);

        Route::get('/reports', [AdminReportController::class, 'index']);
        Route::get('/reports/{plainte}', [AdminReportController::class, 'show']);
        Route::patch('/reports/{plainte}/treated', [AdminReportController::class, 'markAsTreated']);
        Route::patch('/reports/{plainte}/ignored', [AdminReportController::class, 'ignore']);

        Route::get('/payments/export', [AdminPaymentController::class, 'export']);
        Route::get('/payments', [AdminPaymentController::class, 'index']);
        Route::get('/payments/{payment}', [AdminPaymentController::class, 'show']);
        Route::get('/payments/{payment}/receipt', [AdminPaymentController::class, 'downloadReceipt']);

        Route::get('/notifications', [AdminNotificationController::class, 'index']);
        Route::patch('/notifications/tout-lire', [AdminNotificationController::class, 'markAllAsRead']);
        Route::patch('/notifications/{notification}/lire', [AdminNotificationController::class, 'markAsRead']);
    });

#déjà tester
    Route::prefix('users')->group(function () {
        Route::post('/logout', [UserController::class, 'logout']);
        Route::post('/profile/photo', [UserController::class, 'changementphoto']);
        Route::put('/updateinformation/{user}', [UserController::class, 'updateinfos']);
        Route::patch('/updatepassword', [UserController::class, 'updatemdp']);
        Route::get('/recherche-artisans', [UserController::class, 'rechercheArtisan']);
    });

    Route::prefix('posts')->group(function () {
        Route::post('/creerposts', [ArtisanController::class, 'createposte']);
        Route::post('/{postid}/like', [LikesController::class, 'like']);
        Route::get('/{post}/commentaires', [CommentairesController::class, 'affichercommentaire']);
        Route::post('/commentaires', [CommentairesController::class, 'postercommentaire']);
    });

    Route::prefix('artisans')->group(function () {
        Route::post('/demande-certification', [ArtisanController::class, 'demandecertification']);
    });
#en cours de teste
    Route::prefix('appeloffres')->group(function () {
        // Route de creation d'appel d'offres: le métier peut être envoyé par id ou par nom.
        Route::post('/appeloffres', [AppeloffreController::class, 'createappeloffre']);
        Route::patch('/closeappel/{id}', [AppeloffreController::class, 'closeappeloffre']);
        #à tester avec un artisan
        Route::patch('/candidatures/{candidature}/accepter', [AppeloffreController::class, 'accepterCandidature']);
        Route::get('/mes-appels-offres', [AppeloffreController::class, 'mesAppelsOffres']);
        Route::get('/feed-appels-offres', [AppeloffreController::class, 'feedAppelsOffres']);
        #à tester avec un artisan
        Route::post('/appels-offres/{appelOffre}/postuler', [AppeloffreController::class, 'postulerAppelOffre']);

    });

    Route::prefix('services')->group(function () {
        Route::post('/services', [ServiceController::class, 'creerService']);
        Route::get('/{service}', [ServiceController::class, 'voirService']);
        Route::patch('/{service}/valider', [ServiceController::class, 'validerService']);
        Route::patch('/{service}/terminer', [ServiceController::class, 'terminerService']);
    });

    Route::prefix('clients')->group(function () {
        Route::get('/{client}/appels-offres', [ClientController::class, 'appelsOffres']);
        Route::get('/{client}/services', [ClientController::class, 'services']);
        Route::get('/{client}/avis', [ClientController::class, 'avis']);
    });

    Route::prefix('avis')->group(function () {
        Route::post('/users/{user}', [AvisController::class, 'store']);
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
        Route::post('/conversations', [MessagerieController::class, 'createConversation']);
        Route::post('/messages/upload', [MessagerieController::class, 'upload']);
        Route::post('/messages/voice/upload', [MessagerieController::class, 'uploadVoiceNote']);
        Route::post('/conversations/{conversation}/messages', [MessagerieController::class, 'store']);
        Route::get('/conversations/{conversation}/messages', [MessagerieController::class, 'index']);
    });

    Route::post('/plaintes', [PlainteController::class, 'plaintes']);
});
