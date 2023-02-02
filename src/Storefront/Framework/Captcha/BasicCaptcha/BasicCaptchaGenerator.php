<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha\BasicCaptcha;

use Shopware\Core\Framework\Util\Random;
use Symfony\Component\Filesystem\Filesystem;

class BasicCaptchaGenerator extends AbstractBasicCaptchaGenerator
{
    private const FALLBACK_WIDTH_IMAGE = 390;
    private const FALLBACK_HEIGHT_IMAGE = 65;

    private string $backgroundPath = __DIR__ . '/../../../Resources/app/storefront/dist/assets/captcha/background.png';

    private string $fontPath = __DIR__ . '/../../../Resources/app/storefront/dist/assets/captcha/font.ttf';

    public function generate(int $length = 7): BasicCaptchaImage
    {
        $code = $this->createCaptchaCode($length);

        $filesystem = new Filesystem();

        if ($filesystem->exists($this->backgroundPath)) {
            /** @var resource $img */
            $img = imagecreatefrompng($this->backgroundPath);
        } else {
            /** @var resource $img */
            $img = imagecreate(self::FALLBACK_WIDTH_IMAGE, self::FALLBACK_HEIGHT_IMAGE);
            imagecolorallocate($img, 255, 255, 255);
        }

        $codeColor = (int) imagecolorallocate($img, 0, 0, 0);
        if ($filesystem->exists($this->fontPath)) {
            imagettftext($img, 45, 0, 80, 55, $codeColor, $this->fontPath, $code);
        } else {
            imagestring($img, 5, 100, 20, $code, $codeColor);
        }

        ob_start();
        imagepng($img, null, 9);
        $image = (string) ob_get_clean();
        imagedestroy($img);
        $image = base64_encode($image);

        return new BasicCaptchaImage($code, $image);
    }

    public function setBackgroundPath(string $path): void
    {
        $this->backgroundPath = $path;
    }

    public function getBackgroundPath(): string
    {
        return $this->backgroundPath;
    }

    public function setFontPath(string $fonts): void
    {
        $this->fontPath = $fonts;
    }

    public function getFontPath(): string
    {
        return $this->fontPath;
    }

    private function createCaptchaCode(int $length): string
    {
        $alphabetRangeLow = range('a', 'z');
        $alphabetRangeUpp = range('A', 'Z');

        $exclude = ['C', 'c', 'I', 'l', 'O', 'o', 's', 'S', 'U', 'u', 'v', 'V', 'W', 'w', 'X', 'x', 'Z', 'z'];

        $alphabet = array_merge($alphabetRangeLow, $alphabetRangeUpp);
        $alphabet = array_diff($alphabet, $exclude);

        $numericRange = range(1, 9);

        $charList = implode('', $alphabet) . implode('', $numericRange);

        return Random::getString($length, $charList);
    }
}
