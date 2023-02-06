<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Inkedus\Captcha;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Utils\Filesystem\Filesystem;
use Hyperf\Utils\Str;
use HyperfExt\Encryption\Crypt;
use Intervention\Image\Gd\Font;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Finder\Finder;

class CaptchaFactory
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var array
     */
    protected $fonts;

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var \Psr\SimpleCache\CacheInterface
     */
    protected $cache;

    /**
     * @var ImageManager
     */
    protected $imageManager;

    /**
     * @var Filesystem
     */
    protected $files;

    /**
     * @var Image
     */
    protected $image;

    protected $hasher;

    /**
     * @var Str
     */
    protected $str;

    protected $canvas;

    /**
     * @var array
     */
    protected $backgrounds = [];

    /**
     * @var array
     */
    protected $fontColors = [];

    /**
     * @var int
     */
    protected $length = 5;

    /**
     * @var int
     */
    protected $width = 120;

    /**
     * @var int
     */
    protected $height = 36;

    /**
     * @var int
     */
    protected $angle = 15;

    /**
     * @var int
     */
    protected $lines = 3;

    /**
     * @var string
     */
    protected $characters;

    /**
     * @var array
     */
    protected $text;

    /**
     * @var int
     */
    protected $contrast = 0;

    /**
     * @var int
     */
    protected $quality = 90;

    /**
     * @var int
     */
    protected $sharpen = 0;

    /**
     * @var int
     */
    protected $blur = 0;

    /**
     * @var bool
     */
    protected $bgImage = true;

    /**
     * @var string
     */
    protected $bgColor = '#ffffff';

    /**
     * @var bool
     */
    protected $invert = false;

    /**
     * @var bool
     */
    protected $sensitive = false;

    /**
     * @var bool
     */
    protected $math = false;

    /**
     * @var int
     */
    protected $textLeftPadding = 1;

    /**
     * @var string
     */
    protected $fontsDirectory;

    /**
     * @var int
     */
    protected $expire = 600;

    /**
     * @var bool
     */
    protected $encrypt = true;

    public function __construct(ConfigInterface $config, CacheInterface $cache, ImageManager $imageManager, Image $image, Filesystem $files)
    {
        $this->config = $config->get('hi_captcha');
        $this->cache = $cache;
        $this->imageManager = $imageManager;
        $this->image = $image;
        $this->files = $files;
        $this->characters = $this->config['characters'] ?? ['1', '2', '3', '4', '6', '7', '8', '9'];
    }

    /**
     * DESCRIPTION:
     * DATE: 2021/11/25
     * TIME: 3:47 下午
     * AUTHOR: hongcoo.
     * @param mixed $type
     * @return array|mixed
     */
    public function make(string $type = 'default', bool $api = true)
    {
        $type = ! empty($type) ? $type : 'default';
        $this->configure($type);
        $this->backgrounds = $this->files->files(__DIR__ . '/../assets/backgrounds');
        $this->fonts = $this->get_fonts();
        $generator = $this->generate();
        $this->text = $generator['value'];

        $this->canvas = $this->imageManager->canvas(
            $this->width,
            $this->height,
            $this->bgColor
        );

        if ($this->bgImage) {
            $this->image = $this->imageManager->make($this->background())->resize(
                $this->width,
                $this->height
            );
            $this->canvas->insert($this->image);
        } else {
            $this->image = $this->canvas;
        }

        if ($this->contrast != 0) {
            $this->image->contrast($this->contrast);
        }

        $this->text();

        $this->lines();

        if ($this->sharpen) {
            $this->image->sharpen($this->sharpen);
        }
        if ($this->invert) {
            $this->image->invert();
        }
        if ($this->blur) {
            $this->image->blur($this->blur);
        }

        return $api ? [
            'sensitive' => $generator['sensitive'],
            'key' => $generator['key'],
            'img' => $this->image->encode('data-url')->encoded,
        ] : $this->image->response('png', $this->quality);
    }

    /**
     * DESCRIPTION: 验证
     * DATE: 2021/11/25
     * TIME: 2:19 下午
     * AUTHOR: hongcoo.
     * @param mixed $value
     * @param mixed $hashedValue
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function validate($value, $hashedValue, array $options = []): bool
    {
        try {
            [$original, $expiresAt] = $this->deCrypt($hashedValue);
            if ($original === strtolower($value)
                && $expiresAt >= time()
                && $this->cache->get($cacheKey = $this->getCacheKey($hashedValue)) === null
            ) {
                $this->cache->set($cacheKey, $expiresAt, $expiresAt - time());
                return true;
            }
        } catch (\Throwable $e) {
        }
        return false;
    }

    /**
     * DESCRIPTION: 生成缓存密钥
     * DATE: 2021/11/25
     * TIME: 2:19 下午
     * AUTHOR: hongcoo.
     */
    protected function getCacheKey(string $key): string
    {
        return 'hi-captcha:' . md5($key);
    }

    /**
     * DESCRIPTION: 解密
     * DATE: 2021/11/25
     * TIME: 2:20 下午
     * AUTHOR: hongcoo.
     */
    protected function deCrypt(string $key): array
    {
        return Crypt::decrypt($key, true, $this->config['encryption_driver']);
    }

    /**
     * Image backgrounds.
     *
     * @return string
     */
    protected function background()
    {
        return $this->backgrounds[rand(0, count($this->backgrounds) - 1)];
    }

    /**
     * Generate captcha text.
     *
     * @throws Exception
     * @throws \Exception
     */
    protected function generate(): array
    {
        $characters = is_string($this->characters) ? str_split($this->characters) : $this->characters;
        $bag = [];

        if ($this->math) {
            $x = random_int(10, 30);
            $y = random_int(1, 9);
            $bag = "{$x} + {$y} = ";
            $key = $x + $y;
            $key .= '';
        } else {
            for ($i = 0; $i < $this->length; ++$i) {
                $char = $characters[rand(0, count($characters) - 1)];
                $bag[] = $this->sensitive ? $char : mb_strtolower($char, 'UTF-8');
            }
            $key = implode('', $bag);
        }

        $hash = $this->enCrypt($key, time() + $this->expire);

        /*$this->cache->set('captcha', [
            'sensitive' => $this->sensitive,
            'key' => $hash,
            'encrypt' => $this->encrypt,
        ]);*/

        return [
            'value' => $bag,
            'sensitive' => $this->sensitive,
            'key' => $hash,
        ];
    }

    /**
     * @param string $type
     */
    protected function configure($type)
    {
        if (isset($this->config[$type])) {
            foreach ($this->config[$type] as $key => $val) {
                $this->{$key} = $val;
            }
        }
    }

    /**
     * Image fonts.
     */
    protected function font()
    {
        return $this->fonts[rand(0, count($this->fonts) - 1)];
    }

    /**
     * Writing captcha text.
     */
    protected function text(): void
    {
        $marginTop = $this->image->height() / $this->length;
        $text = $this->text;
        if (is_string($text)) {
            $text = str_split($text);
        }
        foreach ($text as $key => $char) {
            $marginLeft = $this->textLeftPadding + ($key * ($this->image->width() - $this->textLeftPadding) / $this->length);

            $this->image->text($char, $marginLeft, $marginTop, function ($font) {
                /* @var Font $font */
                $font->file($this->font());
                $font->size($this->fontSize());
                $font->color($this->fontColor());
                $font->align('left');
                $font->valign('top');
                $font->angle($this->angle());
            });
        }
    }

    /**
     * Random image lines.
     *
     * @return Image|ImageManager
     */
    protected function lines()
    {
        for ($i = 0; $i <= $this->lines; ++$i) {
            $this->image->line(
                rand(0, $this->image->width()) + $i * rand(0, $this->image->height()),
                rand(0, $this->image->height()),
                rand(0, $this->image->width()),
                rand(0, $this->image->height()),
                function ($draw) {
                    /* @var Font $draw */
                    $draw->color($this->fontColor());
                }
            );
        }

        return $this->image;
    }

    /**
     * Random font size.
     */
    protected function fontSize(): int
    {
        return rand($this->image->height() - 10, $this->image->height());
    }

    /**
     * Random font color.
     */
    protected function fontColor(): string
    {
        if (! empty($this->fontColors)) {
            $color = $this->fontColors[rand(0, count($this->fontColors) - 1)];
        } else {
            $color = '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
        }

        return $color;
    }

    /**
     * Angle.
     */
    protected function angle(): int
    {
        return rand(-1 * $this->angle, $this->angle);
    }

    /**
     * DESCRIPTION: 加密
     * DATE: 2021/11/25
     * TIME: 2:20 下午
     * AUTHOR: hongcoo.
     * @throws \Exception
     */
    protected function enCrypt(string $text, int $expiresAt): string
    {
        return Crypt::encrypt([strtolower($text), $expiresAt, random_bytes(16)], true, $this->config['encryption_driver']);
    }

    private function get_fonts()
    {
        $fonts = [];
        $path = $this->config['fonts_dir'];
        $finder = Finder::create()->in($path)->files()->name('*.ttf')->name('*.otf');
        foreach ($finder as $item) {
            $fonts[] = $item->getRealPath();
        }
        return $fonts;
    }
}
