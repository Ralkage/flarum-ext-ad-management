<?php

namespace Ralkage\AdManagement\Api\Controller;

use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Ralkage\AdManagement\Api\Serializer\AdSerializer;
use Ralkage\AdManagement\Model\Ad;
use Ralkage\AdManagement\Service\AdService;
use Tobscure\JsonApi\Document;

class CreateAdController extends AbstractCreateController
{
    public $serializer = AdSerializer::class;

    public $include = ['zone', 'owner'];

    protected $service;
    protected $mailer;
    protected $settings;

    public function __construct(AdService $service, Mailer $mailer, SettingsRepositoryInterface $settings)
    {
        $this->service = $service;
        $this->mailer = $mailer;
        $this->settings = $settings;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        if (!$actor->isAdmin() && !$actor->hasPermission('ralkage-ad-management.submitAd')) {
            $actor->assertAdmin(); // throws PermissionDeniedException
        }

        $data = Arr::get($request->getParsedBody(), 'data.attributes', []);

        $ad = $this->service->createAd($data, $actor);

        // Notify admin when a non-admin submits an ad for review
        if (!$actor->isAdmin() && $ad->status === 'pending_review') {
            $this->notifyAdmin($ad, $actor->display_name);
        }

        return $ad;
    }

    private function notifyAdmin(Ad $ad, string $ownerName): void
    {
        $adminEmail = $this->settings->get('mail_from', '');
        if (!$adminEmail) {
            return;
        }

        $forumTitle = $this->settings->get('forum_title', 'Forum');
        $forumUrl = rtrim((string) $this->settings->get('url', ''), '/');
        $adName = $ad->name;

        $body = "Hello,\n\nA new advertisement \"{$adName}\" has been submitted by {$ownerName} and is awaiting review.\n\nTo review it, visit the Ad Management panel:\n{$forumUrl}/admin\n\n{$forumTitle}";
        $subject = "[{$forumTitle}] New ad pending review: \"{$adName}\"";

        try {
            $this->mailer->raw($body, function (Message $message) use ($adminEmail, $subject) {
                $message->to($adminEmail);
                $message->subject($subject);
            });
        } catch (\Exception $e) {
            // Don't fail the request if the notification email fails
        }
    }
}
