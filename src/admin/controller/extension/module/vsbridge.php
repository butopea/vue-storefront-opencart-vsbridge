<?php
class ControllerExtensionModuleVsbridge extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/vsbridge');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('vsbridge', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=module', true));
        }

        $data['heading_title'] = $this->language->get('heading_title');

        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');

        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_secret_key'] = $this->language->get('entry_secret_key');
        $data['entry_endpoint_statuses'] = $this->language->get('entry_endpoint_statuses');

        $data['info_secret_key'] = $this->language->get('info_secret_key');
        $data['info_endpoint_statuses'] = $this->language->get('info_endpoint_statuses');

        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['button_generate_secret_key'] = $this->language->get('button_generate_secret_key');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['secret_key'])) {
            $data['error_secret_key'] = $this->error['secret_key'];
        } else {
            $data['error_secret_key'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_module'),
            'href' => $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/vsbridge', 'token=' . $this->session->data['token'], true)
        );

        $data['action'] = $this->url->link('extension/module/vsbridge', 'token=' . $this->session->data['token'], true);

        $data['cancel'] = $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=module', true);

        $default_endpoint_statuses = array(
            'attributes' => true,
            'auth' => true,
            'cart' => true,
            'categories' => true,
            'order' => true,
            'product' => true,
            'products' => true,
            'stock' => true,
            'sync_session' => true,
            'taxrules' => true,
            'user' => true
        );

        $data['vsbridge_status'] = $this->request->post['vsbridge_status'] ?? $this->config->get('vsbridge_status');

        $data['vsbridge_secret_key'] = $this->request->post['vsbridge_secret_key'] ?? $this->config->get('vsbridge_secret_key');

        if (isset($this->request->post['vsbridge_endpoint_statuses'])) {
            $data['vsbridge_endpoint_statuses'] = $this->request->post['vsbridge_endpoint_statuses'];
        } elseif(!empty($this->config->get('vsbridge_endpoint_statuses'))) {
            $data['vsbridge_endpoint_statuses'] = $this->config->get('vsbridge_endpoint_statuses');
        } else {
            $data['vsbridge_endpoint_statuses'] = $default_endpoint_statuses;
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/vsbridge', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/vsbridge')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (empty($this->request->post['vsbridge_secret_key'])) {
            $this->error['secret_key'] = $this->language->get('error_secret_key');
        }else{
            if(!preg_match('/^.*(?=.{12,}+)(?=.*[0-9]+)(?=.*[A-Z]+)(?=.*[a-z]+)(?=.*[\*&!@%\^#\$]+).*$/', $this->request->post['vsbridge_secret_key'])){
                $this->error['secret_key'] = $this->language->get('error_secret_key');
            }
        }

        return !$this->error;
    }

    public function install()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "vsbridge_token` (
                              `vsbridge_token_id` int(11) NOT NULL,
                              `customer_id` int(11) NOT NULL,
                              `token` varchar(32) NOT NULL,
                              `ip` varchar(40) NOT NULL,
                              `timestamp` int(11) NOT NULL
                            ) ENGINE = InnoDB DEFAULT CHARSET = utf8;");

        $this->db->query("ALTER TABLE
                              `" . DB_PREFIX . "vsbridge_token`
                            ADD
                              PRIMARY KEY (`vsbridge_token_id`),
                            ADD
                              UNIQUE KEY `token` (`token`);");

        $this->db->query("ALTER TABLE
                          `" . DB_PREFIX . "vsbridge_token` MODIFY `vsbridge_token_id` int(11) NOT NULL AUTO_INCREMENT;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "vsbridge_refresh_token` (
                              `vsbridge_refresh_token_id` int(11) NOT NULL,
                              `customer_id` int(11) NOT NULL,
                              `ip` varchar(40) NOT NULL,
                              `timestamp` int(11) NOT NULL
                            ) ENGINE = InnoDB DEFAULT CHARSET = utf8;");

        $this->db->query("ALTER TABLE
                              `" . DB_PREFIX . "vsbridge_refresh_token`
                            ADD
                              PRIMARY KEY (`vsbridge_refresh_token_id`);");

        $this->db->query("ALTER TABLE
                          `" . DB_PREFIX . "vsbridge_refresh_token` MODIFY `vsbridge_refresh_token_id` int(11) NOT NULL AUTO_INCREMENT;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "vsbridge_session` (
                              `customer_id` int(11) NOT NULL,
                              `store_id` int(11) NOT NULL,
                              `session_id` varchar(32) NOT NULL
                            ) ENGINE = InnoDB DEFAULT CHARSET = utf8;");

        $this->db->query("ALTER TABLE
                              `" . DB_PREFIX . "vsbridge_session`
                            ADD
                              UNIQUE `unique_index`(`customer_id`, `store_id`);");
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "vsbridge_token`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "vsbridge_refresh_token`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "vsbridge_session`");
    }
}