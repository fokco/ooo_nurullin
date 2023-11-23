<?php
//    require 'vendor/autoload.php';

final class Ip {
    private string $ip;
    private bool $is_ip4;

    public function __construct(string $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new ValueError('IP is not correct');
        }
        $this->ip = $ip;
        $this->is_ip4 = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4
        );
    }

    public function getIp(): string {
        return $this->ip;
    }

    public function isIpV4(): bool {
        return $this->is_ip4;
    }

    public function isIpV6(): bool {
        return !$this->is_ip4;
    }
}

final class Point
{
    private float $lat;
    private float $lng;

    public function __construct(float $lat, float $lng)
    {
        $this->lat = $lat;
        $this->lng = $lng;
    }

    public function getLat(): float
    {
        return $this->lat;
    }

    public function getLng(): float
    {
        return $this->lng;
    }
}

final class Location {
    private Ip $ip;
    private string $country;
    private string $zip;
    private Point $point;
    private string $city;

    public function __construct(
        Ip $ip,
        string $country,
        string $city,
        string $zip,
        Point $point
    ) {
        $this->ip = $ip;
        $this->country = $country;
        $this->zip = $zip;
        $this->point = $point;
        $this->city = $city;
    }

    public function getIp(): Ip
    {
        return $this->ip;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getZip(): string
    {
        return $this->zip;
    }

    public function getPoint(): Point
    {
        return $this->point;
    }

    public function getCity(): string
    {
        return $this->city;
    }
}

interface Locator {
    public function __construct(Requester $requester);
    public function locate(Ip $ip): Location | null;
}

abstract class LocatorService implements Locator {
    protected Requester $requester;

    public function __construct(Requester $requester)
    {
        $this->requester = $requester;
    }
}

interface Requester {
    public function request(string $url): array | null;
}

abstract class ARequester implements Requester {
    private string $base_dir = __DIR__ . '/cache';
    public function __construct() {
        if (!file_exists($this->base_dir) || !is_dir($this->base_dir)) {
            mkdir($this->base_dir, 0755, true);
        }
    }

    protected function save(string $url, array $data) {
        $filename = md5($url) . '.json';
        $file = $this->base_dir . '/' . $filename;
        file_put_contents(
            $file,
            json_encode($data)
        );
    }

    public function request(string $url): array|null
    {
        $filename = md5($url) . '.json';
        $file = $this->base_dir . '/' . $filename;
        if (file_exists($file) && is_file($file)) {
            return json_decode(
                file_get_contents($file),
                true
            );
        }
        return null;
    }
}

final class FileGetRequester implements Requester {
    public function request(string $url): array|null
    {
        try {
            $json = file_get_contents($url);
            $json = json_decode($json, true);
            return $json;
        } catch (\Exception $e) {
            return null;
        }
    }
}

//    final class GuzzleRequester extends ARequester implements Requester {
//        public function request(string $url): array|null
//        {
//            $cache = parent::request($url);
//            if (!$cache) {
//                echo 'Knock knock ' . $url;
//                $client = new \GuzzleHttp\Client();
//                $request = $client->get($url);
//                if ($request->getStatusCode() !== 200)
//                    return null;
//                $cache = json_decode(
//                    $request->getBody()->getContents(),
//                    true
//                );
//                parent::save($url, $cache);
//            }
//            return $cache;
//        }
//    }

final class IpApiComService extends LocatorService implements Locator {

    public function locate(Ip $ip): Location|null
    {
        $url = 'http://ip-api.com/json/' . $ip->getIp();
        $json = $this->requester->request($url);
        if (!$json)
            return null;
        $point = new Point(
            $json['lat'],
            $json['lon'],
        );
        return new Location(
            $ip,
            $json['country'],
            $json['city'],
            $json['zip'],
            $point
        );
    }
}

final class FreeIpApiService extends LocatorService implements Locator {
    public function locate(Ip $ip): Location|null
    {
        $url = 'https://freeipapi.com/api/json/' . $ip->getIp();
        $json = $this->requester->request($url);
        if (!$json)
            return null;
        $point = new Point(
            $json['latitude'],
            $json['longitude'],
        );
        return new Location(
            $ip,
            $json['countryName'],
            $json['cityName'],
            $json['zipCode'],
            $point
        );
    }
}
final class ipwho extends LocatorService implements Locator {
    public function locate(Ip $ip): Location|null
    {
        $url = 'https://ipwho.is/' . $ip->getIp();
        $json = $this->requester->request($url);
        if (!$json)
            return null;
        $point = new Point(
            $json['latitude'],
            $json['longitude'],
        );
        return new Location(
            $ip,
            $json['country'],
            $json['city'],
            $json['postal'],
            $point
        );
    }
}

$requester = new FileGetRequester();
$ip = new Ip('46.191.148.255');
$s1 = new FreeIpApiService($requester);
$s2 = new IpApiComService($requester);
$s3 = new ipwho($requester);


foreach ([$s1,$s2,$s3] as $service) {
    $info = $service->locate($ip);
    if ($info !== null) {
        echo get_class($service) . PHP_EOL;
        print_r($info->getCountry());
        echo PHP_EOL;
        print_r($info->getCity());
        echo PHP_EOL;
        print_r($info->getZip());
        echo PHP_EOL;
        print_r($info->getPoint()->getLat());
        echo PHP_EOL;
        print_r($info->getPoint()->getLng());
        echo PHP_EOL;
        echo PHP_EOL;
    }
}
