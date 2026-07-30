<?php
require_once(__DIR__ . '/revolut.php');

class ControllerExtensionPaymentRevolutPrb extends ControllerExtensionPaymentRevolut
{
    private $error = array();

    public function index()
    {
        $this->gateway = "revolut_prb";

        $this->load->language('extension/payment/revolut');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->load->model('extension/payment/revolut');

            // Always re-run domain registration on Save (do not trust a stale OK).
            $domain_result = $this->applePayDomainRegistration(true);
            $this->request->post['payment_revolut_prb_apple_pay_domain'] = $domain_result['status'];

            $this->model_setting_setting->editSetting('payment_revolut_prb', $this->request->post);

            if ($domain_result['status'] === 'OK') {
                $this->session->data['success'] = $this->language->get('text_success')
                    . ' Apple Pay domain: OK (' . $domain_result['domain'] . ', HTTP '
                    . $domain_result['http_code'] . ').';
            } else {
                $this->session->data['success'] = $this->language->get('text_success')
                    . ' Apple Pay domain: FAILED — ' . $domain_result['message']
                    . ' (check system/storage/logs/error.log). Status can stay Disabled; domain registration still runs on Save.';
            }

            $this->response->redirect($this->url->link('extension/payment/revolut_prb', 'user_token=' . $this->session->data['user_token'], true));
        }

        $configuration['success'] = isset($this->session->data['success']) ? $this->session->data['success'] : '';
        unset($this->session->data['success']);
        $configuration['error_api_key_config'] = !$this->setupWebhook() ? $this->language->get('error_api_key_config') : '';

        $configuration['breadcrumbs'] = $this->getBreadcrumbs();
        $configuration['action'] = $this->url->link('extension/payment/revolut_prb', 'user_token=' . $this->session->data['user_token'], true);
        $configuration['cancel'] = $this->url->link('extension/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
        $configuration['header'] = $this->load->controller('common/header');
        $configuration['column_left'] = $this->load->controller('common/column_left');
        $configuration['footer'] = $this->load->controller('common/footer');
        $configuration['user_token'] = $this->session->data['user_token'];

        $configuration['payment_revolut_prb_total'] = $this->getFromPostOrConfig('payment_revolut_prb_total', 0);

        $configuration['payment_revolut_prb_geo_zone_id'] = $this->getFromPostOrConfig('payment_revolut_prb_geo_zone_id');
        $configuration['payment_revolut_prb_status'] = $this->getFromPostOrConfig('payment_revolut_prb_status');
        $configuration['payment_revolut_prb_sort_order'] = $this->getFromPostOrConfig('payment_revolut_prb_sort_order', 1);
        $configuration['payment_revolut_prb_apple_pay_domain'] = $this->getFromPostOrConfig('payment_revolut_prb_apple_pay_domain', 'KO');

        $host = '';
        if (defined('HTTPS_CATALOG') && HTTPS_CATALOG) {
            $parsed = parse_url(HTTPS_CATALOG);
            $host = isset($parsed['host']) ? $parsed['host'] : '';
        }
        $configuration['apple_pay_domain_host'] = $host;
        $configuration['apple_pay_association_url'] = $host
            ? ('https://' . $host . '/.well-known/apple-developer-merchantid-domain-association')
            : '';

        $this->load->model('localisation/geo_zone');
        $configuration['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $this->response->setOutput($this->load->view('extension/payment/revolut_prb', $configuration));
    }

    public function install()
    {
    }

    public function uninstall()
    {
    }

    private function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/revolut')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    /**
     * @param bool $force Re-register even if previously marked OK
     * @return array{status:string,domain:string,http_code:int|string,message:string}
     */
    public function applePayDomainRegistration($force = false)
    {
        $result = array(
            'status'    => 'KO',
            'domain'    => '',
            'http_code' => 0,
            'message'   => 'unknown',
        );

        if (!$force && $this->getFromPostOrConfig('payment_revolut_prb_apple_pay_domain', 'KO') == 'OK') {
            $this->log->write('Apple pay merchant already onboarded');
            $result['status'] = 'OK';
            $result['message'] = 'already onboarded';
            return $result;
        }

        try {
            $this->downloadOnboardingFile();

            $oc_domain = parse_url(HTTPS_CATALOG);
            $host = isset($oc_domain['host']) ? $oc_domain['host'] : '';
            $result['domain'] = $host;

            if ($host === '') {
                throw new \Exception('Cannot onboard Apple pay merchant: empty HTTPS_CATALOG host');
            }

            $this->load->model('extension/payment/revolut');
            $register_result = $this->model_extension_payment_revolut->registerApplePayDomain($host);
            $this->log->write('Apple pay merchant onboarding result: ' . json_encode($register_result));

            // Keep .well-known file — Apple may re-verify the domain.

            $http_code = 0;
            if (is_array($register_result) && isset($register_result['http_code'])) {
                $http_code = (int)$register_result['http_code'];
            }
            $result['http_code'] = $http_code;

            $response = (is_array($register_result) && array_key_exists('response', $register_result))
                ? $register_result['response']
                : $register_result;

            if (!empty($response) && is_array($response) && isset($response['code'])) {
                throw new \Exception('Cannot onboard Apple pay merchant: ' . $response['code']);
            }

            // Revolut returns 204 No Content on success; 409 = already registered.
            if ($http_code !== 200 && $http_code !== 204 && $http_code !== 409) {
                throw new \Exception('Apple pay merchant onboarding failed with HTTP code: ' . ($http_code ?: 'unknown'));
            }

            $result['status'] = 'OK';
            $result['message'] = ($http_code === 409) ? 'already registered' : 'registered';
            return $result;
        } catch (\Exception $e) {
            $this->log->write($e->getMessage());
            $result['message'] = $e->getMessage();
            return $result;
        }
    }

    public function downloadOnboardingFile()
    {
        $domain_onboarding_file_name = 'apple-developer-merchantid-domain-association';
        $domain_onboarding_file_directory = '.well-known';
        $opencart_root_dir = rtrim(dirname(rtrim(DIR_CATALOG, '/')), '/') . '/';

        $onboarding_file_dir = $opencart_root_dir . $domain_onboarding_file_directory;
        $onboarding_file_path = $onboarding_file_dir . '/' . $domain_onboarding_file_name;

        // File already on the live site — do not re-download/overwrite.
        // Stock plugin failed here when allow_url_fopen/permissions blocked rewrite,
        // even though nginx already served the association file with HTTP 200.
        if (is_file($onboarding_file_path) && filesize($onboarding_file_path) > 0) {
            return $onboarding_file_path;
        }

        if (!is_dir($onboarding_file_dir) && !@mkdir($onboarding_file_dir, 0755, true)) {
            throw new \Exception('Apple Pay .well-known directory missing and could not be created: ' . $onboarding_file_dir);
        }

        $contents = false;

        $candidates = array(
            $opencart_root_dir . '.well-known/' . $domain_onboarding_file_name,
            dirname(DIR_APPLICATION) . '/.well-known/' . $domain_onboarding_file_name,
            DIR_SYSTEM . 'storage/download/' . $domain_onboarding_file_name,
        );

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && filesize($candidate) > 0) {
                $contents = file_get_contents($candidate);
                if ($contents !== false && $contents !== '') {
                    break;
                }
            }
        }

        if ($contents === false || $contents === '') {
            $remote = 'https://assets.revolut.com/api-docs/merchant-api/files/apple-developer-merchantid-domain-association';
            $contents = @file_get_contents($remote);
        }

        if ($contents === false || $contents === '') {
            throw new \Exception(
                'Apple Pay association file missing at ' . $onboarding_file_path
                . ' and download failed. Upload it manually to /.well-known/ then Save again.'
            );
        }

        $written = @file_put_contents($onboarding_file_path, $contents);
        if ($written === false || (int)$written <= 0) {
            if (is_file($onboarding_file_path) && filesize($onboarding_file_path) > 0) {
                return $onboarding_file_path;
            }
            throw new \Exception(
                'Cannot write Apple Pay association file to ' . $onboarding_file_path
                . ' (permission denied?). Keep the existing public file and ensure PHP can read it.'
            );
        }

        return $onboarding_file_path;
    }
}
