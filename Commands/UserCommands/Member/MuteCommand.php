<?php
/**
 * Created by PhpStorm.
 * User: Azhe
 * Date: 05/08/2018
 * Time: 10.43
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use ErrorException;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use src\Handlers\ChatHandler;
use src\Model\Group;
use src\Model\Translator;
use src\Utils\Converters;

class MuteCommand extends UserCommand
{
	protected $name = 'mute';
	protected $description = 'Reply to ban member';
	protected $usage = '<mute>';
	protected $version = '1.0.0';
	
	/**
	 * Execute command
	 *
	 * @return ServerResponse|void
	 * @throws TelegramException
	 * @throws ErrorException
	 */
	public function execute()
	{
		$message = $this->getMessage();
		$chatHandler = new ChatHandler($message);
		$chat_id = $message->getChat()->getId();
		$from_id = $message->getFrom()->getId();
		$repMssg = $message->getReplyToMessage();
		
		$isAdmin = Group::isAdmin($from_id, $chat_id);
		
		if ($repMssg != '') {
			$id_target = $repMssg->getFrom()->getId();
		} else {
			$id_target = $from_id;
		}
//		$isSudoer = Group::isSudoer($from_id);
		
		if ($isAdmin || $from_id == $id_target) {
			$chatHandler->sendText('Sedang menge-Mute ' . $id_target);
			$kick = $chatHandler->restrictMember($id_target, 365);
			$text = '<b>Status: </b> ' . Converters::intToString($kick->getOk());
			
			if ($kick->isOk()) {
				$text .= "\n<b>Message: </b> Mute <code>$id_target</code> berhasil..";
			} else {
				$text .= "\n<b>Message: </b> " . $kick->getErrorCode() . ': ' .
					Translator::To($kick->getDescription(), 'id');
			}
		} else {
			$text = '🚫 <i>Kamu tidak memiliki akses /kick</i>';
		}
		
		return $chatHandler->editText($text);
	}
}
