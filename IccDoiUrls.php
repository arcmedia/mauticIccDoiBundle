<?php

declare(strict_types=1);

namespace MauticPlugin\IccDoiBundle;

final class IccDoiUrls
{
    const ACTIVATION_URLS = [
        'de' => 'https://news.starline.de/opt-in-page',  
    ];

    const UNSUBSCRIBE_URLS = [
        'de' => 'https://stage-news.starline.de/opt-out-landingpage',
    ];

    const UNSUBSCRIBE_FORM_URLS = [
        'de' => 'https://stage-news.starline.de/opt-out-page',
    ];

    const SET_EMAIL_TYPE_ROUTE  = 'iccdoi/emailtype';
}