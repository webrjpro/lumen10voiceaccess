<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * classes/ai/router.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_lumen10voiceaccess\ai;
defined('MOODLE_INTERNAL') || die();

use mod_lumen10voiceaccess\preferences;

/** AI can only nominate a bounded, read/navigation intent, never a click or confirmation. */
final class router {
    public const INTENTS = ['READ_PAGE', 'READ_HEADINGS', 'WHERE_AM_I', 'BRIEFING', 'SCROLL_DOWN', 'SCROLL_UP',
        'OPEN_COURSES', 'OPEN_DASHBOARD', 'OPEN_GRADES', 'OPEN_CALENDAR', 'OPEN_MESSAGES', 'OPEN_NOTIFICATIONS',
        'OPEN_PROFILE', 'OPEN_PREFERENCES', 'READ_GRADES', 'LIST_COURSES', 'LIST_ACTIVITIES', 'LIST_SECTIONS',
        'NEXT_ACTIVITY', 'PREVIOUS_ACTIVITY', 'NEXT_SECTION', 'PREVIOUS_SECTION', 'EXPAND_SECTIONS',
        'NEXT_HEADING', 'PREVIOUS_HEADING', 'VIDEO_PLAY', 'VIDEO_PAUSE', 'VIDEO_RESTART', 'VIDEO_MUTE',
        'VIDEO_UNMUTE', 'VIDEO_VOLUME_UP', 'VIDEO_VOLUME_DOWN', 'VIDEO_STATUS',
        'LIST_CONTROLS', 'STOP', 'HELP', 'UNKNOWN',
        'OPEN_ITEM_1', 'OPEN_ITEM_2', 'OPEN_ITEM_3', 'OPEN_ITEM_4', 'OPEN_ITEM_5', 'OPEN_ITEM_6', 'OPEN_ITEM_7',
        'OPEN_ITEM_8', 'OPEN_ITEM_9', 'OPEN_ITEM_10', 'OPEN_ITEM_11', 'OPEN_ITEM_12', 'OPEN_ITEM_13', 'OPEN_ITEM_14',
        'OPEN_ITEM_15', 'OPEN_ITEM_16', 'OPEN_ITEM_17', 'OPEN_ITEM_18', 'OPEN_ITEM_19', 'OPEN_ITEM_20'];

    public static function validate(string $text): array {
        try {
            $data = json_decode(trim($text), true, 8, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return ['intent' => 'UNKNOWN'];
        }
        if (!is_array($data) || !isset($data['intent']) || !is_string($data['intent']) ||
                !in_array($data['intent'], self::INTENTS, true) ||
                !isset($data['confidence']) || !is_numeric($data['confidence']) ||
                (float)$data['confidence'] < 0.85 || (float)$data['confidence'] > 1) {
            return ['intent' => 'UNKNOWN'];
        }
        // Deliberately discard URLs, selectors, arguments and provider-authored explanations.
        return ['intent' => $data['intent']];
    }

    public static function interpret(int $userid, string $command, string $reason = 'unknown'): array {
        global $DB, $CFG;
        if (!\mod_lumen10voiceaccess\access::allowed($userid) || !get_config(preferences::COMPONENT, 'allowai')) {
            return ['intent' => 'UNKNOWN'];
        }
        if (\core_text::strlen($command) > 300 || trim($command) === '') {
            throw new \moodle_exception('invalidcommand', preferences::COMPONENT);
        }
        if (preg_match('/senha|password|api.?key|secret|token|credencia[il]|chave de api|\bsk-[A-Za-z0-9_-]{20,}\b|\bAIza[A-Za-z0-9_-]{20,}|\bAQ\.[A-Za-z0-9_.-]{20,}/iu', $command)) {
            return ['intent' => 'UNKNOWN'];
        }
        $factory = \core\lock\lock_config::get_lock_factory(preferences::COMPONENT);
        $lock = $factory->get_lock('ai-' . $userid, 0);
        if (!$lock) {
            throw new \moodle_exception('ratelimited', preferences::COMPONENT);
        }
        try {
            if (!\mod_lumen10voiceaccess\access::allowed($userid)) {
                return ['intent' => 'UNKNOWN'];
            }
            $row = preferences::get($userid);
            $ai = preferences::effective_ai($row);
            if (empty($ai->consent) || empty($ai->apikey) ||
                    !in_array($ai->provider, ['openai', 'anthropic', gemini::PROVIDER], true)) {
                return ['intent' => 'UNKNOWN'];
            }
            if (!preg_match('/^[a-zA-Z0-9._:\/-]{1,100}$/D', $ai->model)) {
                throw new \moodle_exception('invalidmodel', preferences::COMPONENT);
            }
            $day = gmdate('Y-m-d');
            $used = $row->quotaday === $day ? (int)$row->quotaused : 0;
            $quota = max(0, min(1000, (int)get_config(preferences::COMPONENT, 'dailyquota')));
            if ($used >= $quota || time() - (int)$row->lastrequest < 3) {
                throw new \moodle_exception('ratelimited', preferences::COMPONENT);
            }
            // Project-wide cap keeps the shared free quota available for everybody.
            if ($ai->provider === gemini::PROVIDER && gemini::project_used_today() >= gemini::project_daily_limit()) {
                throw new \moodle_exception('ratelimited', preferences::COMPONENT);
            }
            // Charge attempts before network I/O. Errors cannot create unbounded billable retries.
            if (empty($row->id)) {
                $row->quotaused = $used + 1;
                $row->quotaday = $day;
                $row->lastrequest = time();
                $row->timemodified = time();
                $row->id = $DB->insert_record('lumen10voiceaccess_user', $row);
            } else {
                $DB->update_record('lumen10voiceaccess_user', (object)[
                    'id' => $row->id, 'quotaused' => $used + 1, 'quotaday' => $day, 'lastrequest' => time(),
                ]);
            }
            require_once($CFG->libdir . '/filelib.php');
            $courseMap = [];
            try {
                $snap = \mod_lumen10voiceaccess\navigation::snapshot($userid, get_site(), 0);
                foreach (array_slice($snap['courses'] ?? [], 0, 20) as $idx => $c) {
                    $courseMap[] = 'OPEN_ITEM_' . ($idx + 1) . '=' . $c['name'];
                }
            } catch (\Throwable $e) {
                // Optional course map.
            }
            $instructions = 'Você é o classificador de navegação por voz de acessibilidade da plataforma educacional Lumen10. ' .
                'Sua tarefa é interpretar o que o estudante falou em português natural e mapear para UMA das intenções permitidas. ' .
                'Retorne EXCLUSIVAMENTE um objeto JSON no formato {"intent": "NOME_DO_INTENT", "confidence": 0.95}. ' .
                'Exemplos de mapeamento conversacional em português: ' .
                '"o que tenho pra hoje", "meu resumo", "ir para o painel", "início", "abrir painel" -> OPEN_DASHBOARD. ' .
                '"quais minhas disciplinas", "meus cursos", "ver matérias", "abrir cursos", "listar matérias" -> OPEN_COURSES. ' .
                '"quanto tirei", "minhas notas", "ver boletim", "qual a minha nota", "abrir notas" -> OPEN_GRADES. ' .
                '"onde estou", "qual página é essa", "em qual tela eu tô", "localização" -> WHERE_AM_I. ' .
                '"leia a página", "leia para mim", "leia a tela", "o que está escrito", "leia o texto" -> READ_PAGE. ' .
                '"quais os títulos", "cabeçalhos", "tópicos desta tela", "ler títulos" -> READ_HEADINGS. ' .
                '"próxima atividade", "próxima aula", "avançar", "continuar", "próximo conteúdo" -> NEXT_ACTIVITY. ' .
                '"aula anterior", "atividade anterior", "voltar aula", "conteúdo anterior" -> PREVIOUS_ACTIVITY. ' .
                '"dar play", "iniciar vídeo", "tocar vídeo", "reproduzir" -> VIDEO_PLAY. ' .
                '"pausar vídeo", "pausar", "parar vídeo", "interromper vídeo" -> VIDEO_PAUSE. ' .
                '"aumentar volume", "mais alto", "som mais alto" -> VIDEO_VOLUME_UP. ' .
                '"abaixar volume", "diminuir volume", "som mais baixo" -> VIDEO_VOLUME_DOWN. ' .
                '"silenciar", "mudo" -> VIDEO_MUTE. ' .
                '"ajuda", "o que posso falar", "quais os comandos", "socorro", "não sei o que fazer" -> HELP. ' .
                '"parar", "silêncio", "chega de falar", "para de ler", "cancela" -> STOP. ' .
                'Se o estudante pedir para abrir um item numérico ou nome de curso/atividade mapeado, use OPEN_ITEM_N. ' .
                'Se for ambíguo, uma pergunta de conteúdo da matéria/aula, resposta de prova, tentativa de alteração de dados ou exclusão, retorne UNKNOWN com confidence 1.0. ' .
                'Intenções permitidas: ' . implode(', ', self::INTENTS) . '.' .
                ($courseMap ? ' Cursos/opções mapeadas: ' . implode('; ', $courseMap) . '.' : '');
            if ($reason === 'targetmissing') {
                $instructions .= ' Um comando local não encontrou um alvo visível único. Recupere com OPEN_ITEM_N, LIST_CONTROLS ou HELP a menos que uma intenção evidente de leitura ou navegação seja solicitada.';
            }
            if ($ai->provider === gemini::PROVIDER) {
                $result = gemini::generate($instructions, $command, self::INTENTS, $ai->model, 15, $userid);
                $validated = self::validate($result['text']);
                return $validated;
            }
            $key = \core\encryption::decrypt($ai->apikey);
            $headers = ['Content-Type: application/json'];
            if ($ai->provider === 'openai') {
                $url = 'https://api.openai.com/v1/responses';
                $headers[] = 'Authorization: Bearer ' . $key;
                $body = ['model' => $ai->model, 'instructions' => $instructions, 'input' => $command,
                    'max_output_tokens' => 256, 'store' => false, 'text' => ['format' => ['type' => 'json_object']]];
                if (str_starts_with($ai->model, 'gpt-6-luna')) {
                    $body['reasoning'] = ['effort' => 'none'];
                }
            } else {
                $url = 'https://api.anthropic.com/v1/messages';
                $headers[] = 'x-api-key: ' . $key;
                $headers[] = 'anthropic-version: 2023-06-01';
                $body = ['model' => $ai->model, 'max_tokens' => 128, 'system' => $instructions,
                    'messages' => [['role' => 'user', 'content' => $command]]];
            }
            $curl = new \curl();
            $curl->setHeader($headers);
            // Fixed endpoints + no redirects prevent credentials reaching arbitrary hosts.
            $response = $curl->post($url, json_encode($body), [
                'CURLOPT_TIMEOUT' => 20, 'CURLOPT_CONNECTTIMEOUT' => 5, 'CURLOPT_FOLLOWLOCATION' => false,
                'CURLOPT_MAXREDIRS' => 0, 'CURLOPT_SSL_VERIFYPEER' => true,
                'CURLOPT_SSL_VERIFYHOST' => 2,
            ]);
            $info = $curl->get_info();
            if ($curl->get_errno() || ($info['http_code'] ?? 0) !== 200 || strlen($response) > 100000) {
                throw new \moodle_exception('providererror', preferences::COMPONENT);
            }
            $result = json_decode($response, true);
            if (!is_array($result)) {
                throw new \moodle_exception('providererror', preferences::COMPONENT);
            }
            $text = '';
            if ($ai->provider === 'openai') {
                $items = $result['output'] ?? [];
                if (!is_array($items)) {
                    throw new \moodle_exception('providererror', preferences::COMPONENT);
                }
                foreach ($items as $item) {
                    if (!is_array($item) || ($item['type'] ?? '') !== 'message' || !is_array($item['content'] ?? null)) {
                        continue;
                    }
                    foreach ($item['content'] ?? [] as $content) {
                        if (is_array($content) && ($content['type'] ?? '') === 'output_text' && is_string($content['text'] ?? null)) {
                            $text .= $content['text'];
                        }
                    }
                }
            } else {
                $items = $result['content'] ?? [];
                if (!is_array($items)) {
                    throw new \moodle_exception('providererror', preferences::COMPONENT);
                }
                foreach ($items as $item) {
                    if (is_array($item) && ($item['type'] ?? '') === 'text' && is_string($item['text'] ?? null)) {
                        $text .= $item['text'];
                    }
                }
            }
            return self::validate($text);
        } finally {
            $lock->release();
        }
    }
}

