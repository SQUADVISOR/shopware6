<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Store\Services;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\ExtensionListingLoader;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('checkout')]
class ExtensionListingLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    private ExtensionListingLoader $extensionListingLoader;

    protected function setUp(): void
    {
        $this->extensionListingLoader = static::getContainer()->get(ExtensionListingLoader::class);
    }

    public function testServerNotReachable(): void
    {
        $this->getStoreRequestHandler()->reset();
        $this->getStoreRequestHandler()->append(function (): void {
            throw new ClientException('', new Request('GET', ''), new Response(500, [], ''));
        });

        $collection = new ExtensionCollection();
        $collection->set('myPlugin', (new ExtensionStruct())->assign(['name' => 'myPlugin', 'label' => 'Label', 'version' => '1.0.0']));
        $collection = $this->extensionListingLoader->load($collection, $this->createAdminStoreContext());

        static::assertCount(1, $collection);
    }

    public function testExternalAreAdded(): void
    {
        $this->getStoreRequestHandler()->reset();
        $this->getStoreRequestHandler()->append(new Response(200, [], '{"data":[]}'));
        $this->getStoreRequestHandler()->append(new Response(200, [], $this->getLicencesJson()));

        $collection = new ExtensionCollection();
        $collection->set('myPlugin', (new ExtensionStruct())->assign(['name' => 'myPlugin', 'label' => 'Label', 'version' => '1.0.0', 'active' => true]));
        $collection->set('myPlugin2', (new ExtensionStruct())->assign(['name' => 'myPlugin2', 'label' => 'Label', 'version' => '1.0.0', 'installedAt' => new \DateTime()]));
        $collection = $this->extensionListingLoader->load($collection, $this->createAdminStoreContext());

        /** @var ExtensionStruct $extension */
        $extension = $collection->get('SwagApp');
        static::assertSame('app', $extension->getType());
        static::assertSame('store', $extension->getSource());
        static::assertCount(8, $collection);
    }

    public function testExternalAreMerged(): void
    {
        $this->getStoreRequestHandler()->reset();
        $this->getStoreRequestHandler()->append(new Response(200, [], '{"data":[]}'));
        $this->getStoreRequestHandler()->append(new Response(200, [], $this->getLicencesJson()));

        $collection = new ExtensionCollection();
        $collection->set('SwagApp', (new ExtensionStruct())->assign(['name' => 'SwagApp', 'label' => 'Label', 'version' => '1.0.0', 'active' => true, 'type' => 'app']));
        $collection = $this->extensionListingLoader->load($collection, $this->createAdminStoreContext());

        /** @var ExtensionStruct $extension */
        $extension = $collection->get('SwagApp');
        static::assertSame('app', $extension->getType());
        static::assertSame('local', $extension->getSource());
        static::assertSame('Description', $extension->getDescription());
        static::assertSame('Short Description', $extension->getShortDescription());
        static::assertSame('2.0.0', $extension->getLatestVersion());
        static::assertCount(6, $collection);
    }

    private function getLicencesJson(): string
    {
        $json = file_get_contents(__DIR__ . '/../_fixtures/responses/my-licenses.json');
        static::assertIsString($json, 'Could not read my-licenses.json file');

        return $json;
    }
}
