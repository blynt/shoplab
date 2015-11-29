#!/usr/bin/env php
<?

# Description: Sync data from WooCommerce stores

require __DIR__ . '/vendor/autoload.php';

mb_internal_encoding('UTF-8');
date_default_timezone_set('UTC');

class WooCommerce {
  private $sqlite;
  private $mongo;
  private $mongo_db;
  private $wcc = false; # WooCommerce client
  private $woo_id;
  private $start_at;
  private $url;

  function __construct() {
    $this->sqlite = new SQLite3(__DIR__ . '/db/woo.sqlite');

    $woo_create = '
      CREATE TABLE IF NOT EXISTS woo
      (
        woo_id INTEGER not null,
        url TEXT not null,
        consumer_key TEXT not null,
        consumer_secret TEXT not null,
        last_run_began_at INTEGER not null default 0,
        last_run_ended_at INTEGER not null default 0,
        deleted_at INT not null default 0,
        PRIMARY KEY (woo_id)
      );';

    $this->sqlite->query($woo_create);

    $this->mongo = new MongoClient();
    $this->mongo_db = $this->mongo->selectDB('woo');
  }

  function __destruct() {
    $this->sqlite->close();
    $this->mongo->close();
  }

  # Add new/updated orders, starting at $start_at, at $batch_size order per run
  private function sync_orders($woo_id, $url, $start_at, $batch_size = 100) {
    echo("Syncing with $url.\n");

    $mongo_orders = $this->mongo_db->selectCollection('orders');

    $t = new DateTime("@$start_at");
    $start_at_t = $t->format(DateTime::RFC3339);
    $start_at_t = mb_substr($start_at_t, 0, mb_strlen($start_at_t)-6) . 'Z';

    $pages = $at_page = 1;

    while ($at_page <= $pages) {
      $result = $this->wcc->orders->get(null, array(
        'filter[updated_at_min]' => $start_at_t,
        'filter[limit]' => $batch_size,
        'filter[offset]' => $at_page*$batch_size));

      if (isset($result['http']['response']['headers']['X-WC-TotalPages'])) {
        $pages = (int) $result['http']['response']['headers']['X-WC-TotalPages'];
      }

      if (isset($result['orders'])) {
        foreach ($result['orders'] as $order) {
          $order['shoplab_user_id'] = $woo_id;
	        $mongo_orders->update(
            array(
              'shoplab_user_id' => $order['shoplab_user_id'],
              'order_number' => $order['order_number']
            ),
            $order,
            array(
              'upsert' => true
            )
          );
        }
      }

      if (0 < $pages) {
        echo("Fetched page $at_page (batch size $batch_size) of $pages.\n");
      } else {
        echo("Nothing new to fetch.\n");
      }

      $at_page++;
    }
  }

  public function sync($woo_id) {
    $woo_api_info = '
      SELECT
        url,
        consumer_key,
        consumer_secret,
        last_run_began_at,
        last_run_ended_at
      FROM woo
      WHERE woo_id = ' . $woo_id . ' AND deleted_at = 0
      LIMIT 1';

    $res = $this->sqlite->query($woo_api_info);

    $api_url = $api_consumer_key = $api_consumer_secret = '';

    while ($row = $res->fetchArray()) {
      $api_url = $row['url'];
      $api_consumer_key = $row['consumer_key'];
      $api_consumer_secret = $row['consumer_secret'];

      $this->start_at = $row['last_run_began_at'];
    }

    if ('' == $api_url) {
        throw new Exception("No WooCommerce store with ID $woo_id exists\n");
    }

    try {
      $this->wcc = new WC_API_Client(
        $api_url,
        $api_consumer_key,
        $api_consumer_secret,
        array(
          'return_as_array' => true,
          'debug' => true,
        )
      );
    } catch (WC_API_Client_Exeption $e) {
      throw new Exception("Could not initialize WooCommerce REST client.");
    }

    $woo_upd_run_start = '
      UPDATE
        woo
      SET last_run_began_at = ' . time() . '
      WHERE woo_id = ' . (int) $woo_id . '
      LIMIT 1';

    $res = $this->sqlite->query($woo_upd_run_start);

    if (false == $res) {
      return false;
    }

    $this->sync_orders($woo_id, $api_url, $this->start_at, 100);

    $woo_upd_run_end = '
      UPDATE
        woo
      SET last_run_ended_at = ' . time() . '
      WHERE woo_id = ' . (int) $woo_id . '
      LIMIT 1';

    $res = $this->sqlite->query($woo_upd_run_end);

    if (false == $res) {
      return false;
    }
    return true;
  }

  public function list_stores() {
    $woo_api_stores = '
      SELECT
        woo_id,
        url
      FROM woo
      ORDER by woo_id ASC';

    $res = $this->sqlite->query($woo_api_stores);

    while ($row = $res->fetchArray()) {
      echo("{$row['woo_id']}: {$row['url']}\n");
    }
  }
}

function usage() {
  global $argv;
  echo("Usage: {$argv[0]} COMMAND\n\nCOMMAND can be one of:\nID: Numeric ID of store\nlist: List stores\n");
}

try {
  $wc = new WooCommerce();

  if (2 > count($argv)) {
    usage();
  }
  else if ('list' == $argv[1]) {
    $wc->list_stores();
  }
  else if (true == is_numeric($argv[1])) {
    $wc->sync((int) $argv[1]);
  }
  else {
    usage();
  }
} catch (Exception $e) {
  die("An error occurred: " . $e->getMessage());
}
