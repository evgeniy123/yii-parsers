<?php

namespace console\extended;

use console\helpers\Utils;
use Symfony\Component\DomCrawler\Crawler;

class AdvizorParser {
    private
        $url = 'https://www.tripadvisor.com/Search?',
        $google_geo_url = 'https://maps.googleapis.com/maps/api/geocode/json?',
        $api_key = 'AIzaSyBGzVzzcFlLS9h79PGhBTDqK_P6PHNnOig',
        $country = null,
        $headers = [
            'Host: www.tripadvisor.com',
            'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:66.0) Gecko/20100101 Firefox/66.0',
            'X-Requested-With: XMLHttpRequest',
            'Content-Type: Application/json; charset=utf-8'
        ],
        $query = [
            'geo' => '',
            'q' => '',
            'o' => 0
        ],
        $parse_url = '',
        $months = [
            'January' => 1,
            'February' => 2,
            'March' => 3,
            'April' => 4,
            'May' => 5,
            'June' => 6,
            'July' => 7,
            'August' => 8,
            'September' => 9,
            'October' => 10,
            'November' => 11,
            'December' => 12
        ];

    public function __construct($country = null, $search = '') {
        $this->country = $country;
        $this->query['q'] = $search;
    }

    /**
     * @param $city
     * @return array
     * @throws \Exception
     */
    public function parse_page_links($city): array {
        $this->query['geo'] = $city;
        $this->query['o'] = 0;

        if (!$page = $this->get_page()) {
            Utils::log($city, $this->country);
            throw new \Exception('Не удалось подключиться к ' . $this->parse_url);
        }

        $document = new Crawler($page);
        if (count($document->filter('.no-results')) || count($document->filter('.error-message')))
            throw new \Exception('Нет ресторанов на странице ' . $this->parse_url);

        $blocks = [];
        foreach ($document->filter('.ui_columns .result-content-columns') as $block)
            $blocks[] = $block;

        if (!count($blocks))
            foreach ($document->filter('.ui_columns .result_wrap') as $block)
                $blocks[] = $block;

        $paginate = $document->filter('.pageNumbers .pageNum');
        if (count($paginate)) {
            $offset = (int) $paginate->last()->attr('data-offset');

            for ($i = 30; $i <= $offset; $i += 30) {
                $this->query['o'] = $i;
                $document = new Crawler($this->get_page());
                $tmp = $document->filter('.ui_columns .result-content-columns');

                if (!count($tmp))
                    $tmp = $document->filter('.ui_columns .result_wrap');

                if (count($tmp))
                    foreach ($tmp as $block)
                        $blocks[] = $block;
            }
        }

        $links = [];
        if (count($blocks))
            foreach ($blocks as $block) {
                preg_match('/\/Restaurant.+\.html/', $block->getAttribute('onclick'), $result);
                $links[] = [':country' => $this->country, ':city' => $city, ':url' => $result[0]];
            }

        return $links;
    }

    public function parse_page($links): array {
        $data = [];
        foreach ($links as $link) {
            $info = [];
            if (!$page = $this->get_page('https://www.tripadvisor.com' . $link['url']))
                continue;

            try {
                $data_json = $this->parse_script($page);
            }
            catch (\Exception $e) {
                Utils::log($e->getMessage(), 'advizor');
                continue;
            }

            if (empty($data_json['name']) || empty($data_json['address_obj']) || empty($data_json['address']))
                continue;

            $info['name_en'] = $data_json['name'];
            $address = $data_json['address_obj'];
            if (isset($address['country']))
                $info['country_name'] = $address['country'];
            else
                $info['country_name'] = null;
            if (isset($data_json['address']))
                $info['address'] = $data_json['address'];
            if (isset($data_json['rating']))
                $info['rating'] = $data_json['rating'];
            else
                $info['rating'] = null;
            if (isset($data_json['display_hours']))
                $info['open_hours'] = $data_json['display_hours'][0]['times'][0];
            else
                $info['open_hours'] = null;

            $document = new Crawler($page);
            $review = $document->filter('.review-container .ratingDate');
            if (count($review)) {
                $review = trim($review->eq(0)->attr('title'));
                preg_match('/([a-zA-Z]+)\ +(\d{2}),?\ +(\d{4})/', $review, $res);

                if (count($res) && array_key_exists($res[1], $this->months))
                    $info['last_review'] = mktime(0, 0, 0, $this->months[$res[1]], (int) $res[2], (int) $res[3]);
                else
                    $info['last_review'] = 0;
            }
            else
                $info['last_review'] = 0;

            try {
                $res = $this->get_geocode($info['address'], $info['country_name']);

                if (isset($res['code']))
                    $info['country_iso2'] = $res['code'];
                if (isset($res['postal_code']))
                    $info['postal_code'] = $res['postal_code'];
                if (isset($res['lat']) && isset($res['lng'])) {
                    $info['latitude_d'] = (float) $res['lat'];
                    $info['longitude_d'] = (float) $res['lng'];
                }
                $info['flag'] = 1;
            }
            catch (\Exception $e) {
                Utils::log($e->getMessage(), 'advizor');
            }

            $info['counter'] = $link['counter'] + 1;
            $info['created_at'] = time();

            $data[] = ['data' => $info, 'id' => $link['id']];
        }

        return $data;
    }

    private function get_page($url = null) {
        if ($url)
            $this->parse_url = $url;
        else
            $this->parse_url = $this->url . 'geo=' . $this->query['geo'] . '&q=' . $this->query['q'] . '&o=' . $this->query['o'] . '&ssrc=e';

        return Utils::curlRequest($this->parse_url, false, $url ? [] : $this->headers);
    }

    /**
     * @param $page
     * @return array
     * @throws \Exception
     */
    private function parse_script($page): array {
        $document = new Crawler($page);
        $scripts = $document->filter('body > script');
        if (!count($scripts))
            throw new \Exception('Не найдены ниодного скрипта на странице ' . $this->parse_url);

        $json_data = [];
        foreach ($scripts as $script) {
            preg_match('/{pageManifest:({.+})}/', $script->textContent, $text);
            if (count($text)) {
                $json_data = json_decode($text[1], true);
                break;
            }
        }

        $json_data = $json_data['redux']['api']['responses'];

        foreach ($json_data as $key => $value) {
            preg_match('/location\/\d+$/', $key, $res);
            if (count($res)) {
                $json_data = $value;
                break;
            }
        }

        return $json_data['data'];
    }

    /**
     * @param $address
     * @param $country_name
     * @return array
     * @throws \Exception
     */
    private function get_geocode($address, $country_name): array {
        $address = str_replace(' ', '+', $address);
        $data = [];
        $result = json_decode($this->get_page($this->google_geo_url . 'address=' . $address . '&key=' . $this->api_key), true);
        if ($result['status'] === 'OK') {
            foreach ($result['results'][0]['address_components'] as $value) {
                if (isset($value['types'][0])) {
                    if ($value['types'][0] == 'country') {
                        $data['country'] = $value['long_name'];
                        $data['code'] = $value['short_name'];
                    }
                    elseif ($value['types'][0] == 'postal_code')
                        $data['postal_code'] = $value['long_name'];
                }
            }

            if ($data['country'] != $country_name)
                throw new \Exception('Google вернул неправильный адрес ' . $this->parse_url);

            $data['lat'] = $result['results'][0]['geometry']['location']['lat'];
            $data['lng'] = $result['results'][0]['geometry']['location']['lng'];
        }
        else
            throw new \Exception('Google ответил отрицательно ' . $this->parse_url . '. ' . 'google status: ' . $result['status']);

        return $data;
    }
}