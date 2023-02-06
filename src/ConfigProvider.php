<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Inkedus\Captcha;

use Inkedus\Captcha\Listener\ValidatorFactoryResolvedListener;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'listeners' => [
                ValidatorFactoryResolvedListener::class,
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for zhuchunshu/hyperf-captcha.',
                    'source' => __DIR__ . '/../publish/hi_captcha.php',
                    'destination' => BASE_PATH . '/config/autoload/hi_captcha.php',
                ],
                [
                    'id' => 'fonts',
                    'description' => 'The fonts for zhuchunshu/hyperf-captcha.',
                    'source' => __DIR__ . '/../publish/fonts',
                    'destination' => BASE_PATH . '/storage/fonts',
                ],
            ]
        ];
    }
}
