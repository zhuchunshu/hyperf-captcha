# Captcha for Hyperf

##### This is the Captcha component for Hyperf 2.


## 安装

```shell
composer require zhuchunshu/hyperf-captcha
```

## 发布配置

```shell
php bin/hyperf.php vendor:publish zhuchunshu/hyperf-captcha
```

> 字体文件默认发布到 `<root>/resources/fonts` 目录。

组件依赖 `hyperf-ext/encryption` 组件加解密 `key`，依赖 `hyperf/cache` 组件暂存使用过的 `key`，您需要发布这些组件的配置：

```shell
php bin/hyperf.php vendor:publish hyperf-ext/encryption
php bin/hyperf.php vendor:publish hyperf/cache
```

## 使用

```php
use Hyperf\Utils\ApplicationContext;
use Inkedus\Captcha\CaptchaFactory;

$captchaFactory = ApplicationContext::getContainer()->get(CaptchaFactory::class);

// 生成
$captcha = $captchaFactory->make();

// 验证
$captchaFactory->validate($key, $text);
```




#### 本包参考并使用以下扩展的部分逻辑及代码，特别感谢
* [Intervention Image](https://github.com/Intervention/image)
* [Mewebstudio Captcha](https://github.com/mewebstudio/captcha)
* [hyperf-ext/captcha](https://github.com/hyperf-ext/captcha)


