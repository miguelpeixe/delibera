<?php

// PHP 5.3 and later:
namespace Delibera\Modules;

class Discussion extends \Delibera\Modules\ModuleBase
{
	
	/**
	 * List of of topic status
	 * @var array
	 */
	public $situacao = array('discussao');
	
	/**
	 *
	 * @var array list of module flows
	 */
	protected $flows = array('discussao');
	
	/**
	 * Name of module deadline metadata
	 * @var String
	 */
	protected $prazo_meta = 'prazo_discussao';
	
	/**
	 * List of pair shotcode name => method
	 * @var array
	 */
	protected $shorcodes = array('delibera_lista_de_pautas' => 'replacePautas' );
	
	/**
	 * Config days to make new deadline
	 * @var array
	 */
	protected $days = array('dias_discussao');
	
	/**
	 * Display priority
	 * @var int
	 */
	public $priority = 2;
	
	/**
	 * Register Tax for the module
	 */
	public function registerTax()
	{
		if(term_exists('discussao', 'situacao', null) == false)
		{
			delibera_insert_term('Pauta em discussão', 'situacao', array(
					'description'=> 'Pauta em Discussão',
					'slug' => 'discussao',
				),
				array(
					'qtrans_term_pt' => 'Pauta em discussão',
					'qtrans_term_en' => 'Agenda en discusión',
					'qtrans_term_es' => 'Topic under discussion',
				)
			);
		}
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \Delibera\Modules\ModuleBase::initModule()
	 */
	public function initModule($post_id)
	{
		wp_set_object_terms($post_id, 'discussao', 'situacao', false);
		$this->newDeadline($post_id);
	}
	
	/**
	 * Append configurations 
	 * @param array $opts
	 */
	public function getMainConfig($opts)
	{
		$opts['pauta_suporta_encaminhamento'] = 'S';
		$opts['dias_discussao'] = '5';
		return $opts;
	}
	
	/**
	 * Array to show on config page
	 * @param array $rows
	 */
	public function configPageRows($rows, $opt)
	{
		$rows[] = array(
			"id" => "dias_discussao",
			"label" => __('Dias para discussão da pauta:', 'delibera'),
			"content" => '<input type="text" name="dias_discussao" id="dias_discussao" value="'.htmlspecialchars_decode($opt['dias_discussao']).'" autocomplete="off" />'
		);
		$rows[] = array(
			"id" => "pauta_suporta_encaminhamento",
			"label" => __('Pautas suportam sugestão de encaminhamento?', 'delibera'),
			"content" => '<input type="checkbox" name="pauta_suporta_encaminhamento" id="pauta_suporta_encaminhamento" value="S" '. ( htmlspecialchars_decode($opt['pauta_suporta_encaminhamento']) != "N" ? "checked='checked'" : "" ).' autocomplete="off" />',
		);
		return $rows;
	}
	
	/**
	 * Label to apply to button
	 * @param unknown $situation
	 */
	public function situationButtonText($situation)
	{
		if($situation == 'discussao')
		{
			return __('Discutir', 'delibera');
		}
		
		return $situation;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \Delibera\Modules\ModuleBase::generateDeadline()
	 */
	public function generateDeadline($options_plugin_delibera)
	{
		$dias_discussao = intval(htmlentities($options_plugin_delibera['dias_discussao']));
		
		$prazo_discussao_sugerido = strtotime("+$dias_discussao days", delibera_tratar_data(\Delibera\Flow::getLastDeadline('discussao')));
		return date('d/m/Y', $prazo_discussao_sugerido);
	}
	
	/**
	 * 
	 * Post Meta Fields display
	 * 
	 * @param \WP_Post $post
	 * @param array $custom post custom fields
	 * @param array $options_plugin_delibera Delibera options array
	 * @param WP_Term $situacao
	 * @param bool $disable_edicao
	 * 
	 */
	public function topicMeta($post, $custom, $options_plugin_delibera, $situacao, $disable_edicao)
	{
		$prazo_discussao = $this->generateDeadline($options_plugin_delibera);
		$tipo_discussao = array_key_exists("tipo_discussao", $custom) ?  $custom["tipo_discussao"][0] : 'forum';
		
		if(!($post->post_status == 'draft' ||
				$post->post_status == 'auto-draft' ||
				$post->post_status == 'pending'))
		{
			$prazo_discussao = array_key_exists("prazo_discussao", $custom) ?  $custom["prazo_discussao"][0] : $prazo_discussao;
		}
		
		?>
		<p>
			<label class="label_prazo_discussao"><?php _e('Prazo para Discussões','delibera') ?>:</label>
			<input <?php echo $disable_edicao ?> name="prazo_discussao" class="prazo_discussao widefat hasdatepicker" value="<?php echo $prazo_discussao; ?>"/>
		</p><?php /*?>
		<p>
			<label class="label_tipo_discussao"><?php _e('Tipo de Discussões','delibera') ?>:</label>
			<input <?php echo $disable_edicao ?> name="tipo_discussao" class="tipo_discussao widefat" value="<?php echo $tipo_discussao; ?>"/>
		</p>
		<?php */
		
	}
	
	public function publishPauta($postID, $opt)
	{
		$events_meta = array();
		$events_meta['delibera_numero_comments_encaminhamentos'] = 0;
		$events_meta['delibera_numero_comments_discussoes'] = 0;
		
		foreach ($events_meta as $key => $value) // Buscar dados
		{
			if(get_post_meta($postID, $key, true)) // Se já existe
			{
				update_post_meta($postID, $key, $value); // Atualiza
			}
			else
			{
				add_post_meta($postID, $key, $value, true); // Senão, cria
			}
		}
		
	}
	
	function checkPostData($errors, $opt, $autosave)
	{
		$value = $_POST['prazo_discussao'];
		$valida = delibera_tratar_data($value);
		if(!$autosave && (empty($value) ||  $valida === false || $valida < 1) )
		{
			$errors[] = __("É necessário definir corretamente o prazo de discussão", "delibera");
		}
		return $errors;
	}
	
	public function replacePautas($matches)
	{
		$temp = explode(',', $matches[1]); // configurações da shorttag
	    $count = count($temp);
	
	    $param = array(); // TODO Tratar Parametros
	
	    $html = self::getPautas($param);
		$wp_posts = $html;
	    global $post;
	    $old = $post;
	    echo '<div id="lista-de-pautas">';
	    foreach ( $wp_posts as $wp_post )
	    {
			$post = $wp_post;
			include 'delibera_loop_pauta.php';
		}
		echo '</div>';
		$post = $old;
	
		return ''; // Retornar código da representação
				
	}
	
	public function savePostMetas($events_meta, $opt, $post_id = false)
	{
		if(array_key_exists('prazo_discussao', $_POST))
		{
			$events_meta['prazo_discussao'] = sanitize_text_field($_POST['prazo_discussao']);
		}
		if(array_key_exists('tipo_discussao', $_POST))
		{
			$events_meta['tipo_discussao'] = sanitize_text_field($_POST['tipo_discussao']);
		}
		
		return $events_meta;
	}
	
	public function createPautaAtFront($opt)
	{
		$data_externa = $this->treatFixedDateToEndExtTopic($opt);
		if ( $data_externa ) {
			$_POST['prazo_discussao'] = $data_externa;
		} else {
			$_POST['prazo_discussao'] = $this->generateDeadline($opt);
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \Delibera\Modules\ModuleBase::deadline()
	 */
	public static function deadline($args)
	{
		$post_id = $args['post_ID'];
		$situacao = delibera_get_situacao($post_id);
		if($situacao->slug == 'discussao')
		{
			if(count(delibera_get_comments_encaminhamentos($post_id)) > 0)
			{
				\Delibera\Flow::next($post_id);
				
				if(has_action('delibera_discussao_concluida'))
				{
					do_action('delibera_discussao_concluida', $post_id);
				}
			}
			else
			{
				$current = \Delibera\Flow::getCurrentModule($post_id);
				$current->newDeadline($post_id, false);
			}
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \Delibera\Modules\ModuleBase::getCommentListLabel()
	 */
	public function getCommentListLabel()
	{
		return __('Discussão sobre a Pauta', 'delibera');
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \Delibera\Modules\ModuleBase::template_redirect()
	 */
	public function template_redirect()
	{
		/*$tipo = get_post_meta(get_the_ID(), 'tipo_discussao', true);
		if($tipo == 'side')
		{
			require_once DELIBERA_DIR_PATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'wp-side-comments' . DIRECTORY_SEPARATOR . 'wp-side-comments.php';
			if(function_exists('\Delibera\Includes\SideComments\wpsc_init_side_comments'))
			{
				\Delibera\Includes\SideComments\wpsc_init_side_comments();
			}
		}*/
	}
	
	public static function treatCommentType($comment, $encaminhamento)
	{
		$comment_id = $comment->comment_ID;
		if($encaminhamento == "S")
		{
			add_comment_meta($comment_id, 'delibera_comment_tipo', 'encaminhamento', true);
			$nencaminhamentos = get_post_meta($comment->comment_post_ID, 'delibera_numero_comments_encaminhamentos', true);
			$nencaminhamentos++;
			update_post_meta($comment->comment_post_ID, 'delibera_numero_comments_encaminhamentos', $nencaminhamentos);
			if(array_key_exists('delibera-baseouseem', $_POST) && !empty($_POST['delibera-baseouseem']))
			{
				add_comment_meta($comment_id, 'delibera-baseouseem', $_POST['delibera-baseouseem'], true);
				$based_list = explode(',', $_POST['delibera-baseouseem']);
				foreach ($based_list as $baseouseem_element)
				{
					$atts = shortcode_parse_atts(stripcslashes($baseouseem_element));
					if(!is_array($atts)) continue;
					if(array_key_exists('id', $atts))
					{
						update_comment_meta($atts['id'], 'delibera-hasbasedon', $comment_id);
					}
				}
			}
		}
		else
		{
			add_comment_meta($comment_id, 'delibera_comment_tipo', 'discussao', true);
			$ndiscussoes = get_post_meta($comment->comment_post_ID, 'delibera_numero_comments_discussoes', true);
			$ndiscussoes++;
			update_post_meta($comment->comment_post_ID, 'delibera_numero_comments_discussoes', $ndiscussoes);
		}
		if(has_action('delibera_nova_discussao'))
		{
			do_action('delibera_nova_discussao', $comment_id, $comment, $encaminhamento);
		}
	}
	
	public static function isEncaminhamento($comment_id)
	{
		return get_comment_meta($comment_id, 'delibera_comment_tipo', true) == 'encaminhamento';
	}
}
$DeliberaDiscussion = new \Delibera\Modules\Discussion();


