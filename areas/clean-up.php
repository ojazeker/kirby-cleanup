<?php

use Kirby\Exception\PermissionException;

return function ($kirby) {
    $guard = static function () use ($kirby): void {
        if ($kirby->user()?->isAdmin() !== true) {
            throw new PermissionException('Only administrators can use the Clean Up tool.');
        }
    };

    return [
        'label' => 'Clean Up',
        'icon' => 'refresh',
        'menu' => $kirby->user()?->isAdmin() === true,
        'link' => 'clean-up',
        'views' => [
            [
                'pattern' => 'clean-up',
                'action' => function () use ($guard) {
                    $guard();

                    return [
                        'component' => 'k-clean-up-view',
                        'title' => 'Clean Up',
                        'props' => ['active' => 'images'],
                    ];
                },
            ],
            [
                'pattern' => 'clean-up/content',
                'action' => function () use ($guard) {
                    $guard();

                    return [
                        'component' => 'k-clean-up-view',
                        'title' => 'Content cleanup',
                        'props' => ['active' => 'content'],
                    ];
                },
            ],
        ],
    ];
};