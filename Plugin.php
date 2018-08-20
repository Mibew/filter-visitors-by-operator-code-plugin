<?php
/*
 * This file is a part of Mibew Filter Visitors By Operator Code Plugin.
 *
 * Copyright 2018 the original author or authors.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Mibew\Mibew\Plugin\FilterVisitorsByOperatorCode;

use Mibew\EventDispatcher\EventDispatcher;
use Mibew\EventDispatcher\Events;
use Mibew\Thread;

/**
 * Shows threads opened with operator code only for specified operator
 */
class Plugin extends \Mibew\Plugin\AbstractPlugin implements \Mibew\Plugin\PluginInterface
{
    /**
     * List of the plugin configs.
     *
     * @var array
     */
    protected $config;

    /**
     * Class constructor.
     *
     * @param array $config List of the plugin config. The following options are
     * supported:
     *   - 'enable_for_supervisors': boolean, hide threads for operators which can view
     *     other operators' threads (i.e. supervisors).
     *     The default value is false.
     */
    public function __construct($config)
    {
        $this->config = $config + array('enable_for_supervisors' => false);
    }

    /**
     * The plugin does not need extra initialization thus it is always ready to
     * work.
     *
     * @return boolean
     */
    public function initialized()
    {
        return true;
    }

    /**
     * Specify version of the plugin.
     *
     * @return string Plugin's version.
     */
    public static function getVersion()
    {
        return '0.1.0';
    }

    /**
     * The main entry point of a plugin.
     */
    public function run()
    {
        $dispatcher = EventDispatcher::getInstance();
        $dispatcher->attachListener(Events::USERS_UPDATE_THREADS_ALTER, $this, 'alterThreads');
    }

    /**
     * Event listener for "usersUpdateThreadsAlter" event.
     */
    public function alterThreads(&$args)
    {

        // Get actual operator
        if (array_key_exists(SESSION_PREFIX . 'operator', $_SESSION)) {
            $operator = operator_by_id($_SESSION[SESSION_PREFIX . 'operator']['operatorid']);
        }
        else {
            return;
        }

        // Do not hide threads for operators which can view all threads
        if (is_capable(CAN_VIEWTHREADS, $operator) && !$this->config['enable_for_supervisors']) {
            return;
        }

        // Process threads list to exclude those started with an operator's code
        foreach($args['threads'] as $key => $thread_info) {

            if ($thread_info['state'] != Thread::STATE_QUEUE) {
                continue;
            }
            $thread = Thread::load($thread_info['id']);
            if ($thread->nextAgent && ($thread->nextAgent != $operator['operatorid'])) {
                unset($args['threads'][$key]);
            }
        }

        // Make keys consequent to avoid problems with JSON conversion. If there
        // will be gaps in keys JSON Object will be produced instead of an Array.
        $args['threads'] = array_values($args['threads']);
    }
}
