<?php
//
//    ______         ______           __         __         ______
//   /\  ___\       /\  ___\         /\_\       /\_\       /\  __ \
//   \/\  __\       \/\ \____        \/\_\      \/\_\      \/\ \_\ \
//    \/\_____\      \/\_____\     /\_\/\_\      \/\_\      \/\_\ \_\
//     \/_____/       \/_____/     \/__\/_/       \/_/       \/_/ /_/
//
//   上海商创网络科技有限公司
//
//  ---------------------------------------------------------------------------------
//
//   一、协议的许可和权利
//
//    1. 您可以在完全遵守本协议的基础上，将本软件应用于商业用途；
//    2. 您可以在协议规定的约束和限制范围内修改本产品源代码或界面风格以适应您的要求；
//    3. 您拥有使用本产品中的全部内容资料、商品信息及其他信息的所有权，并独立承担与其内容相关的
//       法律义务；
//    4. 获得商业授权之后，您可以将本软件应用于商业用途，自授权时刻起，在技术支持期限内拥有通过
//       指定的方式获得指定范围内的技术支持服务；
//
//   二、协议的约束和限制
//
//    1. 未获商业授权之前，禁止将本软件用于商业用途（包括但不限于企业法人经营的产品、经营性产品
//       以及以盈利为目的或实现盈利产品）；
//    2. 未获商业授权之前，禁止在本产品的整体或在任何部分基础上发展任何派生版本、修改版本或第三
//       方版本用于重新开发；
//    3. 如果您未能遵守本协议的条款，您的授权将被终止，所被许可的权利将被收回并承担相应法律责任；
//
//   三、有限担保和免责声明
//
//    1. 本软件及所附带的文件是作为不提供任何明确的或隐含的赔偿或担保的形式提供的；
//    2. 用户出于自愿而使用本软件，您必须了解使用本软件的风险，在尚未获得商业授权之前，我们不承
//       诺提供任何形式的技术支持、使用担保，也不承担任何因使用本软件而产生问题的相关责任；
//    3. 上海商创网络科技有限公司不对使用本产品构建的商城中的内容信息承担责任，但在不侵犯用户隐
//       私信息的前提下，保留以任何方式获取用户信息及商品信息的权利；
//
//   有关本产品最终用户授权协议、商业授权与技术服务的详细内容，均由上海商创网络科技有限公司独家
//   提供。上海商创网络科技有限公司拥有在不事先通知的情况下，修改授权协议的权力，修改后的协议对
//   改变之日起的新授权用户生效。电子文本形式的授权协议如同双方书面签署的协议一样，具有完全的和
//   等同的法律效力。您一旦开始修改、安装或使用本产品，即被视为完全理解并接受本协议的各项条款，
//   在享有上述条款授予的权力的同时，受到相关的约束和限制。协议许可范围以外的行为，将直接违反本
//   授权协议并构成侵权，我们有权随时终止授权，责令停止损害，并保留追究相关责任的权力。
//
//  ---------------------------------------------------------------------------------
//
defined('IN_ECJIA') or exit('No permission resources.');

/**
 * 计划任务安装API
 * @author royalwang
 */
class cron_plugin_install_api extends Component_Event_Api {
	
	public function call(&$options) {
		if (isset($options['file'])) {
			$plugin_file = $options['file'];
			$plugin_data = RC_Plugin::get_plugin_data($plugin_file);
			 
			$plugin_file = RC_Plugin::plugin_basename($plugin_file);
			$plugin_dir = dirname($plugin_file);
			 
			$plugins = ecjia_config::instance()->get_addon_config('cron_plugins', true);
			$plugins[$plugin_dir] = $plugin_file;
			 
			ecjia_config::instance()->set_addon_config('cron_plugins', $plugins, true);
		}
		
		if (isset($options['config']) && !empty($plugin_data['Name'])) {

			Ecjia\App\Cron\Helper::assign_adminlog_content();
			
			$format_name = $plugin_data['Name'];
			$format_description = $plugin_data['Description'];
			
			/* 检查输入 */
			if (empty($format_name) || empty($options['config']['cron_code'])) {
				return ecjia_plugin::add_error('plugin_install_error', RC_Lang::get('cron::cron.plugin_name_empty'));
			}

			/* 检测名称重复 */
			$name_count = RC_DB::table('crons')->where('cron_name', $format_name)->where('cron_code', $options['config']['cron_code'])->count();
			if ($name_count > 0) {
				return ecjia_plugin::add_error('plugin_install_error', RC_Lang::get('cron::cron.plugin_exist'));
			}
			
			/* 取得配置信息 */
			$cron_config = serialize($options['config']['forms']);
			$cron_config_file = $options['config'];
			
			//判断是否有默认执行时间配置
			if (array_get($cron_config_file, 'lock_time', false)) {
			    $cron_expression  = array_get($cron_config_file['default_time'], 'cron_expression', '');
			    $expression_alias = array_get($cron_config_file['default_time'], 'expression_alias', '');
			    
			    $file_list = with(new Ecjia\App\Cron\CronExpression)->getProvidesMultipleRunDates($cron_expression);
			    foreach ($file_list as $key => $value) {
			    	$file_list[$key] = (array)$value;
			    }
			    foreach ($file_list as $key => $value) {
			    	$mydate = new DateTime($value['date']);
			    	$new_date = $mydate->format('Y-m-d H:i:s');
			    	$file_list[$key]['new_date'] = $new_date;
			    }
			    $nexttime = RC_Time::local_strtotime($file_list[0][new_date]);
			} else {
			    $cron_expression  = '';
			    $expression_alias = '';
			    $nexttime = 0;
			}
			
			/* 执行后关闭 */
			$cron_run_once = 0;
			
			$allow_ip    = '';
		
			/* 安装，检查该支付方式是否曾经安装过 */
			$count = RC_DB::table('crons')->where('cron_code', $options['config']['cron_code'])->count();
			
			if ($count > 0) {
				/* 该支付方式已经安装过, 将该支付方式的状态设置为 enable */
				$data = array(
					'cron_name' 		=> $format_name,
					'cron_desc'     	=> $format_description,
					'cron_config' 		=> $cron_config,
					'cron_expression' 	=> $cron_expression,	
					'expression_alias' 	=> $expression_alias,
				    'nexttime' 			=> $nexttime,
				    'run_once' 			=> $cron_run_once,
				    'allow_ip' 			=> $allow_ip,
					'enabled' 			=> 1
				);
				RC_DB::table('crons')->where('cron_code', $options['config']['cron_code'])->update($data);
				
			} else {
				/* 该支付方式没有安装过, 将该支付方式的信息添加到数据库 */
				$data = array(
					'cron_code' 		=> $options['config']['cron_code'],
					'cron_name' 		=> $format_name,
					'cron_desc' 		=> $format_description,
					'cron_config' 		=> $cron_config,
					'cron_expression' 	=> $cron_expression,
					'expression_alias' 	=> $expression_alias,
				    'nexttime' 		    => $nexttime,
				    'run_once' 		    => $cron_run_once,
				    'allow_ip' 		    => $allow_ip,
					'enabled' 		    => 1,
				);
				RC_DB::table('crons')->insert($data);
			}
			
			/* 记录日志 */
			ecjia_admin::admin_log($format_name, 'install', 'cron');
			return true;
		}
	}
}

// end