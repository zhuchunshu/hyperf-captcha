<?php
/**
 * DESCRIPTION:
 * DATE: 2021/11/25
 * TIME: 7:18 下午
 * AUTHOR: hongcoo
 * PROJECT: tuoke_api
 */

namespace Irooit\Captcha\Listener;

use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Event\ValidatorFactoryResolved;
use Irooit\Captcha\CaptchaFactory;

class ValidatorFactoryResolvedListener implements ListenerInterface
{
    /**
     * DESCRIPTION:
     * DATE: 2021/11/25
     * TIME: 7:26 下午
     * AUTHOR: hongcoo
     * @return string[]
     */
    public function listen(): array
    {
        return [
            ValidatorFactoryResolved::class,
        ];
    }

    public function process(object $event)
    {
        /**  @var ValidatorFactoryInterface $validatorFactory */
        $validatorFactory = $event->validatorFactory;

        $validatorFactory->extend('captcha', function ($attribute, $value, $parameters, $validator) {
            if (is_string($value) && strpos($value, ',') !== false) {
                [$ket, $text] = array_pad(explode(',', $value), 2, '');
                return ApplicationContext::getContainer()->get(CaptchaFactory::class)->validate($ket, $text);
            }
            return false;
        });
    }
}
