<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\ConversationModel;
use App\Models\MessageModel;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class MessagerieController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                ], 401);
            }
            $validated = $request->validate([
                'media' => ['required', 'file', 'max:20480'],
            ]);
            /** @var UploadedFile $file */
            $file = $validated['media'];
            $path = $file->store("messages/tmp/user-{$user->id}", 'public');
            return response()->json([
                'success' => true,
                'message' => 'Fichier uploadé avec succès.',
                'data' => [
                    'kind' => $this->guessMediaKind($file->getClientMimeType()),
                    'file_name' => $file->hashName(),
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'url' => asset('storage/' . $path),
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l upload du fichier.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function uploadVoiceNote(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                ], 401);
            }

            $validated = $request->validate([
                'voice_note' => ['required', 'file', 'max:20480'],
            ]);

            /** @var UploadedFile $file */
            $file = $validated['voice_note'];

            if (! $this->isAudioMimeType($file->getClientMimeType())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le fichier doit etre un fichier audio valide.',
                ], 422);
            }

            $path = $file->store("messages/tmp/user-{$user->id}", 'public');

            return response()->json([
                'success' => true,
                'message' => 'Note vocale uploadée avec succès.',
                'data' => [
                    'kind' => 'voice_note',
                    'file_name' => $file->hashName(),
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'url' => asset('storage/' . $path),
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l upload de la note vocale.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function store(Request $request, ConversationModel $conversation): JsonResponse
    {
        $storedPaths = [];

        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                ], 401);
            }

            if (! $this->userBelongsToConversation($user->id, $conversation)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas envoyer de message dans cette conversation.',
                ], 403);
            }

            $validated = $request->validate([
                'content' => ['nullable', 'string', 'max:5000'],
                'media' => ['nullable'],
                'voice_note' => ['nullable'],
            ]);

            $content = trim((string) ($validated['content'] ?? ''));
            $mediaInput = $this->normalizeMediaInput($request->input('media', $validated['media'] ?? null));
            $voiceNoteInput = $this->normalizeMediaInput($request->input('voice_note', $validated['voice_note'] ?? null));
            $mediaPayload = [];

            foreach ($mediaInput as $item) {
                $mediaPayload[] = $this->promoteMediaItem($item, $conversation->id, $storedPaths);
            }

            foreach ($voiceNoteInput as $item) {
                $mediaPayload[] = $this->promoteMediaItem($item, $conversation->id, $storedPaths, 'voice_note');
            }

            if ($content === '' && empty($mediaPayload)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le message doit contenir du texte ou un media.',
                ], 422);
            }

            $otherParticipant = $conversation->otherParticipantFor($user->id);

            if (! $otherParticipant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette conversation ne contient pas de second participant valide.',
                ], 422);
            }

            $messageKind = $this->guessMessageKind($content, $mediaPayload);

            $message = DB::transaction(function () use ($conversation, $user, $content, $mediaPayload, $otherParticipant, $messageKind) {
                $message = MessageModel::create([
                    'conversation_id' => $conversation->id,
                    'expediteur_id' => $user->id,
                    'destinataire_id' => $otherParticipant->id,
                    'kind' => $messageKind,
                    'content' => $content !== '' ? $content : null,
                    'media' => $mediaPayload ?: null,
                ]);

                $destinataires = collect([$conversation->userOne, $conversation->userTwo])
                    ->filter(fn ($participant) => $participant && (int) $participant->id !== $user->id)
                    ->values();

                $notifications = $destinataires->map(function ($destinataire) use ($message, $user) {
                    return [
                        'user_id' => $destinataire->id,
                        'type' => 'nouveau_message',
                        'data_json' => [
                            'message_id' => $message->id,
                            'conversation_id' => $message->conversation_id,
                            'sender_id' => $user->id,
                            'sender_name' => $user->name,
                            'kind' => $message->kind,
                            'content' => $message->content,
                            'media' => $message->media,
                        ],
                    ];
                })->all();

                if ($notifications) {
                    app(NotificationService::class)->sendMany($notifications);
                }

                return $message->load('user', 'conversation', 'destinataire');
            });

            broadcast(new MessageSent($message))->toOthers();

            return response()->json([
                'success' => true,
                'message' => 'Message envoyé avec succès.',
                'data' => $message,
                'media_urls' => collect($message->media ?? [])->pluck('url')->values()->all(),
            ], 201);
        } catch (ValidationException $e) {
            foreach ($storedPaths as $storedPath) {
                Storage::disk('public')->delete($storedPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            foreach ($storedPaths as $storedPath) {
                Storage::disk('public')->delete($storedPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l envoi du message.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function index(Request $request, ConversationModel $conversation): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                ], 401);
            }

            if (! $this->userBelongsToConversation($user->id, $conversation)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas consulter cette conversation.',
                ], 403);
            }

            $messages = MessageModel::query()->where('conversation_id', $conversation->id)->with('user', 'conversation', 'destinataire')->orderBy('created_at')->get();

            return response()->json([
                'success' => true,
                'message' => 'Historique des messages recupere avec succes.',
                'data' => $messages,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la recuperation des messages.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function conversations(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                ], 401);
            }

            $conversations = ConversationModel::query()->where(function ($query) use ($user) {
                    $query->where('user_1_id', $user->id)->orWhere('user_2_id', $user->id);
                })
                ->with([
                    'userOne',
                    'userTwo',
                    'messages' => function ($query) {
                        $query->latest()->limit(1);
                    },
                ])
                ->latest()
                ->get()
                ->map(fn (ConversationModel $conversation) => $this->formatConversation($conversation));

            return response()->json([
                'success' => true,
                'message' => 'Conversations recuperees avec succes.',
                'data' => $conversations,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la recuperation des conversations.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function createConversation(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                ], 401);
            }

            $validated = $request->validate([
                'destinataire_id' => ['required', 'integer', 'exists:users,id'],
                'type' => ['nullable', 'in:private'],
                'title' => ['nullable', 'string', 'max:255'],
            ]);

            $destinataireId = (int) $validated['destinataire_id'];

            if ($destinataireId === (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas creer une conversation avec vous-meme.',
                ], 422);
            }

            $firstUserId = min((int) $user->id, $destinataireId);
            $secondUserId = max((int) $user->id, $destinataireId);

            $conversation = ConversationModel::query()
                ->where(function ($query) use ($user, $destinataireId) {
                    $query->where('user_1_id', $user->id)
                        ->where('user_2_id', $destinataireId);
                })
                ->orWhere(function ($query) use ($user, $destinataireId) {
                    $query->where('user_1_id', $destinataireId)
                        ->where('user_2_id', $user->id);
                })
                ->first();

            if (! $conversation) {
                $conversation = ConversationModel::create([
                    'title' => $validated['title'] ?? null,
                    'type' => 'private',
                    'user_1_id' => $firstUserId,
                    'user_2_id' => $secondUserId,
                ]);
            }

            $conversation->load([
                'userOne',
                'userTwo',
                'messages' => function ($query) {
                    $query->latest()->limit(1);
                },
            ]);

            return response()->json([
                'success' => true,
                'message' => $conversation->wasRecentlyCreated
                    ? 'Conversation creee avec succes.'
                    : 'Conversation existante recuperee avec succes.',
                'data' => $this->formatConversation($conversation),
            ], $conversation->wasRecentlyCreated ? 201 : 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la creation de la conversation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    private function userBelongsToConversation(int $userId, ConversationModel $conversation): bool
    {
        return $conversation->containsUser($userId);
    }
    private function formatConversation(ConversationModel $conversation): array
    {
        $participants = collect([$conversation->userOne, $conversation->userTwo])
            ->filter()
            ->values();

        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'type' => $conversation->type,
            'users' => $participants,
            'last_message' => $conversation->messages->first(),
            'created_at' => $conversation->created_at,
            'updated_at' => $conversation->updated_at,
        ];
    }
    private function normalizeMediaInput(mixed $mediaInput): array
    {
        if (is_string($mediaInput)) {
            $decoded = json_decode($mediaInput, true);
            return is_array($decoded) ? $decoded : [];
        }
        if ($mediaInput instanceof UploadedFile) {
            return [$mediaInput];
        }
        if (is_array($mediaInput)) {
            return $mediaInput;
        }
        return [];
    }
    private function promoteMediaItem(mixed $item, int $conversationId, array &$storedPaths, ?string $forcedKind = null): array
    {
        if ($item instanceof UploadedFile) {
            if ($forcedKind === 'voice_note' && ! $this->isAudioMimeType($item->getClientMimeType())) {
                throw ValidationException::withMessages([
                    'voice_note' => 'La note vocale doit etre un fichier audio valide.',
                ]);
            }

            $tempPath = $item->store("messages/tmp/user-upload", 'public');
            $storedPaths[] = $tempPath;
            $finalPath = "messages/conversation-{$conversationId}/" . basename($tempPath);
            Storage::disk('public')->move($tempPath, $finalPath);
            $storedPaths[count($storedPaths) - 1] = $finalPath;
            return [
                'kind' => $forcedKind ?? $this->guessMediaKind($item->getClientMimeType()),
                'original_name' => $item->getClientOriginalName(),
                'file_name' => $item->hashName(),
                'file_path' => $finalPath,
                'mime_type' => $item->getClientMimeType(),
                'size' => $item->getSize(),
                'url' => asset('storage/' . $finalPath),
            ];
        }
        if (! is_array($item) || empty($item['file_path'])) {
            throw ValidationException::withMessages([
                'media' => 'Chaque media doit contenir un file_path valide.',
            ]);
        }
        $tempPath = $item['file_path'];
        if (! Storage::disk('public')->exists($tempPath)) {
            throw ValidationException::withMessages([
                'media' => 'Le fichier temporaire est introuvable.',
            ]);
        }
        $finalPath = "messages/conversation-{$conversationId}/" . basename($tempPath);
        Storage::disk('public')->move($tempPath, $finalPath);
        $storedPaths[] = $finalPath;
        return [
            'kind' => $forcedKind ?? $this->guessMediaKind($item['mime_type'] ?? null),
            'original_name' => $item['original_name'] ?? basename($tempPath),
            'file_name' => $item['file_name'] ?? basename($tempPath),
            'file_path' => $finalPath,
            'mime_type' => $item['mime_type'] ?? null,
            'size' => $item['size'] ?? null,
            'url' => asset('storage/' . $finalPath),
        ];
    }
    private function guessMessageKind(string $content, array $mediaPayload): string
    {
        if ($content !== '' && ! empty($mediaPayload)) {
            return 'mixed';
        }

        if (collect($mediaPayload)->contains(fn (array $item) => ($item['kind'] ?? null) === 'voice_note')) {
            return 'voice_note';
        }

        if (! empty($mediaPayload)) {
            return 'media';
        }

        return 'text';
    }
    private function guessMediaKind(?string $mimeType): string
    {
        if ($this->isAudioMimeType($mimeType)) {
            return 'voice_note';
        }

        if (is_string($mimeType) && str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (is_string($mimeType) && str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        return 'attachment';
    }
    private function isAudioMimeType(?string $mimeType): bool
    {
        if (! is_string($mimeType) || $mimeType === '') {
            return false;
        }

        $normalizedMimeType = strtolower(trim(explode(';', $mimeType)[0]));

        return in_array($normalizedMimeType, [
            'audio/aac',
            'audio/mp3',
            'audio/mpeg',
            'audio/mp4',
            'audio/ogg',
            'audio/wave',
            'audio/wav',
            'audio/webm',
            'audio/x-m4a',
            'audio/x-wav',
            'video/webm',
        ], true);
    }
}
