<?php

declare(strict_types=1);

namespace MauticPlugin\IccDoiBundle\Controller;

use Mautic\CoreBundle\Controller\AjaxController;
use MauticPlugin\IccDoiBundle\Helper\DoiStatusHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EmailTypeController extends AjaxController
{
    public function __construct(DoiStatusHelper $doiStatusHelper)
    {
        parent::__construct();
        $this->doiStatusHelper = $doiStatusHelper;
    }

    /**
     * @return JsonResponse
     */
    public function setAction(Request $request): JsonResponse
    {
        $type = $request->query->get('type');
        $metaParam = $request->query->get('meta');

        if ($type === null || $metaParam === null) {
            return $this->sendJsonResponse(['error' => 'Missing Params']);
        }

        $meta = json_decode(base64_decode($metaParam), true);
        if (!is_array($meta)) {
            return $this->sendJsonResponse(['error' => 'Invalid meta payload'], 400);
        }

        $leadId = $meta["leadId"] ?? null;
        $token = $meta["token"] ?? null;

        if ($leadId === null || $token === null) {
            return $this->sendJsonResponse(['error' => 'Invalid Params']);
        }

        $leadDoi = $this->doiStatusHelper->setDoiType($leadId, $token, $type);

        if ($leadDoi) {
            return $this->sendJsonResponse('success', Response::HTTP_OK);
        }

        return $this->sendJsonResponse("link invalid", Response::HTTP_UNAUTHORIZED);
    }
}

