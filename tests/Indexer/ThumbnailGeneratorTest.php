<?php

declare(strict_types=1);

namespace MediasIndex\Tests\Indexer;

use MediasIndex\Indexer\ThumbnailGenerator;
use PHPUnit\Framework\TestCase;

final class ThumbnailGeneratorTest extends TestCase
{
    use FixtureTree;

    protected function tearDown(): void
    {
        $this->removeTree();
    }

    private function thumbsDir(): string
    {
        return $this->tree() . '/thumbs';
    }

    private function generator(int $width = 400, int $height = 300): ThumbnailGenerator
    {
        return new ThumbnailGenerator($this->thumbsDir(), $width, $height);
    }

    /** @return string absolute path of the written image */
    private function sourceImage(string $relative, int $width, int $height, string $format = 'jpeg'): string
    {
        $path = $this->tree() . '/' . $relative;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0o775, true);
        }

        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 120, 180));
        $format === 'png' ? imagepng($image, $path) : imagejpeg($image, $path);
        imagedestroy($image);

        return $path;
    }

    public function testProducesAJpegOfExactlyTheConfiguredSize(): void
    {
        $source = $this->sourceImage('media/cover.jpg', 1200, 800);

        $filename = $this->generator()->generate(7, $source);

        self::assertNotNull($filename);
        $info = getimagesize($this->thumbsDir() . '/' . $filename);
        self::assertSame([400, 300], [$info[0], $info[1]]);
        self::assertSame(IMAGETYPE_JPEG, $info[2]);
    }

    /** A portrait source must still fill the box, not letterbox inside it. */
    public function testCoversTheBoxWhateverTheSourceAspectRatio(): void
    {
        $source = $this->sourceImage('media/tall.jpg', 300, 1500);

        $filename = $this->generator()->generate(1, $source);

        $info = getimagesize($this->thumbsDir() . '/' . $filename);
        self::assertSame([400, 300], [$info[0], $info[1]]);
    }

    public function testUpscalesASourceSmallerThanTheBox(): void
    {
        $source = $this->sourceImage('media/tiny.png', 40, 30, 'png');

        $filename = $this->generator()->generate(2, $source);

        $info = getimagesize($this->thumbsDir() . '/' . $filename);
        self::assertSame([400, 300], [$info[0], $info[1]]);
    }

    public function testCreatesTheThumbnailsDirectoryWhenMissing(): void
    {
        self::assertDirectoryDoesNotExist($this->thumbsDir());

        $this->generator()->generate(1, $this->sourceImage('media/a.jpg', 100, 100));

        self::assertDirectoryExists($this->thumbsDir());
    }

    public function testTheSameSourceIsNotRegenerated(): void
    {
        $source = $this->sourceImage('media/a.jpg', 600, 400);

        $first = $this->generator()->generate(3, $source);
        $mtime = filemtime($this->thumbsDir() . '/' . $first);

        touch($this->thumbsDir() . '/' . $first, 1_000_000);
        $second = $this->generator()->generate(3, $source);

        self::assertSame($first, $second);
        self::assertSame(1_000_000, filemtime($this->thumbsDir() . '/' . $second));
        self::assertNotSame(0, $mtime);
    }

    /**
     * The filename carries a digest of the source, so a changed image can never
     * be served from the previous cache entry. The superseded file is removed
     * rather than left to accumulate.
     */
    public function testAChangedSourceYieldsANewFilenameAndDropsTheOldOne(): void
    {
        $source = $this->sourceImage('media/a.jpg', 600, 400);
        $first = $this->generator()->generate(4, $source);

        $this->sourceImage('media/a.jpg', 900, 600);
        $second = $this->generator()->generate(4, $source);

        self::assertNotSame($first, $second);
        self::assertFileDoesNotExist($this->thumbsDir() . '/' . $first);
        self::assertFileExists($this->thumbsDir() . '/' . $second);
    }

    public function testThumbnailsOfOtherMediasAreLeftAlone(): void
    {
        $a = $this->generator()->generate(5, $this->sourceImage('media/a.jpg', 600, 400));
        $b = $this->generator()->generate(6, $this->sourceImage('media/b.jpg', 500, 500));

        $this->sourceImage('media/a.jpg', 800, 800);
        $this->generator()->generate(5, $this->tree() . '/media/a.jpg');

        self::assertFileExists($this->thumbsDir() . '/' . $b);
        self::assertFileDoesNotExist($this->thumbsDir() . '/' . $a);
    }

    public function testDiscardingRemovesEveryThumbnailOfAMedia(): void
    {
        $filename = $this->generator()->generate(9, $this->sourceImage('media/a.jpg', 600, 400));

        $this->generator()->discardPreviousVersions(9);

        self::assertFileDoesNotExist($this->thumbsDir() . '/' . $filename);
    }

    public function testDifferentBoxSizesDoNotShareACacheEntry(): void
    {
        $source = $this->sourceImage('media/a.jpg', 600, 400);

        $small = (new ThumbnailGenerator($this->thumbsDir(), 100, 100))->generate(8, $source);
        $large = (new ThumbnailGenerator($this->thumbsDir(), 400, 300))->generate(8, $source);

        self::assertNotSame($small, $large);
    }

    public function testAMissingSourceProducesNothing(): void
    {
        self::assertNull($this->generator()->generate(1, $this->tree() . '/nope.jpg'));
    }

    /** A corrupt or mislabelled file must not take the whole scan down. */
    public function testAFileThatIsNotAnImageProducesNothing(): void
    {
        $this->writeFile('media/not-an-image.jpg', 'this is plain text');

        self::assertNull($this->generator()->generate(1, $this->tree() . '/media/not-an-image.jpg'));
    }

    public function testAnEmptyFileProducesNothing(): void
    {
        $this->writeFile('media/empty.jpg', '');

        self::assertNull($this->generator()->generate(1, $this->tree() . '/media/empty.jpg'));
    }
}
