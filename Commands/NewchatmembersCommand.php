<?php
/**
 * Created by PhpStorm.
 * User: Azhe
 * Date: 05/08/2018
 * Time: 03.55
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Request;
use src\Handlers\MessageHandlers;
use src\Model\Group;
use src\Model\Settings;
use src\Utils\Time;
use src\Utils\Words;

class NewchatmembersCommand extends SystemCommand
{
	/**
	 * Command execute method
	 *
	 * @return \Longman\TelegramBot\Entities\ServerResponse
	 * @throws \Longman\TelegramBot\Exception\TelegramException
	 */
	public function execute()
	{
		$message = $this->getMessage();
		$chat_id = $message->getChat()->getId();
		$members = $message->getNewChatMembers();
		$chat_title = $message->getChat()->getTitle();
		$chat_username = $message->getChat()->getUsername();
//		$pinned_msg = $message->getPinnedMessage()->getMessageId();
		$mHandler = new MessageHandlers($message);
		$isKicked = false;
		
		// Perika apakah Aku harus keluar grup?
		if (isRestricted
			&& !$message->getChat()->isPrivateChat()
			&& Group::isMustLeft($message->getChat()->getId())) {
			$mHandler->sendText('Sepertinya saya salah alamat. Saya pamit dulu..' . "\nGunakan @WinTenBot");
			return Request::leaveChat(['chat_id' => $chat_id]);
		}
		
		if ($message->botAddedInChat() || $message->getNewChatMembers()) {
			$member_names = [];
			$member_nounames = [];
			$member_bots = [];
			$member_lnames = [];
			$time_current = Time::sambuts();
			$new_welcome_message = '';
			$member_count = json_decode(Request::getChatMembersCount(['chat_id' => $chat_id]), true)['result'];
			$welcome_data = Settings::getNew(['chat_id' => $chat_id]);
			
			foreach ($members as $member) {
				$full_name = trim($member->getFirstName() . ' ' . $member->getLastName());
				$nameLen = strlen($full_name);
				$nameLink = "<a href='tg://user?id=" . $member->getId() . "'>" . $full_name . '</a>';
				if ($nameLen < 140) {
					if ($member->getUsername() === null) {
						$member_nounames[] = $nameLink;
						$no_username_count = count($member_nounames);
						$no_username = implode(', ', $member_nounames);
					} elseif ($member->getIsBot() === true) {
						$member_bots [] = $nameLink . ' 🤖';
						$new_bots_count = count($member_bots);
						$new_bots = implode(', ', $member_bots);
					} else {
						$member_names[] = $nameLink;
						$new_members_count = count($member_names);
						$new_members = implode(', ', $member_names);
					}
				} else {
					$member_lnames [] = $nameLink;
					$data = [
						'chat_id' => $chat_id,
						'user_id' => $member->getId(),
					];
					$isKicked = Request::kickChatMember($data);
					$isKicked = json_decode($isKicked, true);
					Request::unbanChatMember($data);
					
					$data = [
						'chat_id'    => $chat_id,
						'message_id' => $message->getMessageId(),
					];
					
					Request::deleteMessage($data);
				}
			}
			
			$welcome_message = explode("\n\n", $welcome_data[0]['welcome_message']);
			if (count($member_names) > 0) {
				if ($welcome_message[0] != '') {
					$new_welcome_message = $welcome_message[0];
				} else {
					$new_welcome_message = "Anggota baru : $new_members_count" .
						"\n👤Hai $new_members, selamat $time_current." .
						"\nSelamat datang di kontrakan $chat_title";
				}
				$new_welcome_message .= "\n\n";
//				$new_welcome_message .= $welcome_message[0] . "\n\n";
			}
			
			if (count($member_bots) > 0) {
				if ($welcome_message[1] != '') {
					$new_welcome_message = $welcome_message[0];
				} else {
					$new_welcome_message = "🤖 Bot baru: {$new_bots_count}" .
						"\nHai {$new_bots}, siapa yang menambahkan kamu?.";
				}
				$new_welcome_message .= "\n\n";
//				$new_welcome_message .= $welcome_message[1] . "\n\n";
			}
			
			if (count($member_nounames) > 0) {
				if ($welcome_message[2] != '') {
					$new_welcome_message = $welcome_message[0];
				} else {
					$new_welcome_message = "⚠ Tanpa username: {$no_username_count}" .
						"\nHai {$no_username}, tolong pasang username." .
						"\nJika tidak tahu caranya, klik tombol di bawah ini.";
				}
				$new_welcome_message .= "\n\n";
//				$new_welcome_message .= $welcome_message[2] . "\n\n";
			}

//			if (count($member_lnames) > 0) {
//				if ($isKicked['ok'] != false) {
//					$text .=
//						'🚷 < b>Ditendang: </b > (<code > ' . count($member_lnames) . ')</code > ' .
//						"\n" . implode(', ', $member_lnames) . ', Namamu panjang gan!';
//				} else {
//					$text .=
//						' < b>Eksekusi : </b > Mencoba untuk menendang spammer' .
//						"\n < b>Status : </b > " . $isKicked['error_code'] .
//						"\n < b>Result : </b > " . $isKicked['description'];
//				}
//			}
			//$text .= "\n < b>Total : </b > " . $chatCount . 'Anggota';
		}
		
		$replacement = [
			'full_name'         => $full_name ?? '',
			'chat_title'        => $chat_title,
			'namelink'          => $nameLink ?? '',
			'new_members_count' => $new_members_count ?? 0,
			'new_members'       => $new_members ?? '',
			'new_bots_count'    => $new_bots_count ?? 0,
			'new_bots'          => $new_bots ?? '',
			'no_username_count' => $no_username_count ?? 0,
			'no_username'       => $no_username ?? '',
			'time_current'      => $time_current ?? '',
			'member_count'      => $member_count ?? 0,
		];
		
		$text = Words::resolveVariable(trim($new_welcome_message), $replacement);
		
		$btn_markup = [];
		if ($welcome_data[0]['welcome_button'] != '') {
			$btn_data = $welcome_data[0]['welcome_button'];
			$btn_datas = explode(',', $btn_data);
			foreach ($btn_datas as $key => $val) {
				$btn_row = explode('|', $val);
				$btn_markup[] = ['text' => $btn_row[0], 'url' => $btn_row[1]];
			}
		}
		
		if ($no_username_count > 0) {
			$btn_markup[] = ['text' => 'Pasang username', 'url' => urlStart . '?start=username'];
		}
		
		$mHandler->deleteMessage($welcome_data[0]['last_welcome_message_id']);
		$r = $mHandler->sendText($text, null, $btn_markup);
		
		Settings::saveNew([
			'last_welcome_message_id' => $r->result->message_id,
			'chat_id'                 => $chat_id,
		], [
			'chat_id' => $chat_id,
		]);
		
		return $r;
	}
}
