<?php
/**
 * Created by PhpStorm.
 * User: Azhe
 * Date: 05/08/2018
 * Time: 03.55
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use App\Grup;
use App\Waktu;
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Request;

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
        $text = '';
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();
        $members = $message->getNewChatMembers();
        $chat_tit = $message->getChat()->getTitle();
        $chat_uname = $message->getChat()->getUsername();
        $isKicked = false;
//        $pinned_msg = $message->getPinnedMessage()->;

        $time = $message->getDate();
        $time1 = Waktu::jedaNew($time);

        // Perika apakah Aku harus keluar grup?
        if (isRestricted
            && !$message->getChat()->isPrivateChat()
            && Grup::isMustLeft($message->getChat()->getId())) {
            $text = "Sepertinya saya salah alamat. Saya pamit dulu.." .
                "\nGunakan @WinTenBot";
            Request::sendMessage([
                'chat_id' => $chat_id,
                'text' => $text,
                'parse_mode' => 'HTML'
            ]);
            return Request::leaveChat(['chat_id' => $chat_id]);
        }

        if ($message->botAddedInChat() || $message->getNewChatMembers()) {
            $member_names = [];
            $member_nounames = [];
            $member_bots = [];
            $member_lnames = [];

            $data = [
                'chat_id' => $chat_id,
                'message_id' => $message->getMessageId()
            ];

            Request::deleteMessage($data);
            foreach ($members as $member) {
                $full_name = trim($member->getFirstName() . ' ' . $member->getLastName());
                $nameLen = strlen($full_name);
                if ($nameLen < 140) {
                    if ($member->getUsername() === null) {
                        $member_nounames[] = "<a href='tg://user?id=" . $member->getId() . "'>" . $full_name . '</a>';
                    } else if ($member->getIsBot() === true) {
                        $member_bots [] = "<a href='tg://user?id=" . $member->getId() . "'>" . $full_name . '</a> 🤖';
                    } else {
                        $member_names[] = "<a href='tg://user?id=" . $member->getId() . "'>" . $full_name . '</a>';
                    }
                } else {
                    $member_lnames [] = "<a href='tg://user?id=" . $member->getId() . "'>" . $full_name . '</a>';
                    $data = [
                        'chat_id' => $chat_id,
                        'user_id' => $member->getId()
                    ];
                    $isKicked = Request::kickChatMember($data);
                    $isKicked = json_decode($isKicked, true);
                    Request::unbanChatMember($data);

                    $data = [
                        'chat_id' => $chat_id,
                        'message_id' => $message->getMessageId()
                    ];

                    Request::deleteMessage($data);
                }
            }

            //$chatCount = json_decode(Request::getChatMembersCount(['chat_id' => $chat_id]), true)['result'];

            if (count($member_names) > 0) {
                $text =
                    "<b>👥 Anggota baru: </b> (<code>" . count($member_names) . ")</code>" .
                    "\nHai " . implode(', ', $member_names) . ', ' . Waktu::sambuts() .
                    "\nSelamat datang di kontrakan <b>" . $chat_tit . '</b>';
            }

            if (count($member_bots) > 0) {
                $text .=
                    "\n\n<b>🤖 Bot baru: </b> (<code>" . count($member_bots) . ")</code>" .
                    "\nHai " . implode(', ', $member_bots) .
                    "\nSiapa yang menambahkan kamu?";
            }

            if (count($member_nounames) > 0) {
                $text .=
                    "\n\n<b>⚠ Tanpa Username: </b> (<code>" . count($member_nounames) . ")</code>" .
                    "\n" . implode(', ', $member_nounames) . ", Tolong pasang username." .
                    "\n<i>Buka aplikasi Telegram > Settings > Username, lalu isi Username-nya.</i>".
                    " Jika belum tau caranya, klik tombol di bawah ini";
            }

            if (count($member_lnames) > 0) {
                if ($isKicked['ok'] != false) {
                    $text .=
                        "🚷 <b>Ditendang: </b> (<code>" . count($member_lnames) . ")</code>" .
                        "\n" . implode(', ', $member_lnames) . ", Namamu panjang gan!";
                } else {
                    $text .=
                        "<b>Eksekusi : </b> Mencoba untuk menendang spammer" .
                        "\n<b>Status : </b>" . $isKicked['error_code'] .
                        "\n<b>Result : </b>" . $isKicked['description'];
                }
            }

            //$text .= "\n<b>Total : </b>" . $chatCount . 'Anggota';
        }

        $btn_data = [
            ['text' => '📢 Channel', 'url' => 'https://t.me/WinTenChannel'],
            ['text' => '📌 Pinned', 'url' => 'https://t.me/' . $chat_uname . '/'],
            ['text' => '🌐 Site', 'url' => 'https://winten.tk']
        ];

        $urlStart = 'https://t.me/'.bot_username.'?start=username';
        if(count($member_nounames) > 0){
            $btn_data[] = ['text' => 'Cara pasang username', 'url' => $urlStart ];
        }

        $btn_data = array_chunk($btn_data, 3);

        $time2 = Waktu::jedaNew($time);
        $time = "\n\n ⏱ " . $time1 . " | ⏳ " . $time2;

        $data = [
            'chat_id' => $chat_id,
            'text' => $text . $time,
            'parse_mode' => 'HTML',
            'reply_markup' => new InlineKeyboard([
                'inline_keyboard' => $btn_data
            ])
        ];

        if ($text !== null) {
            return Request::sendMessage($data);
        }
    }
}
