# Lumen10 VoiceAccess — componente único

**Assistência por voz e teclado em todo o Moodle, com entrada opcional dentro dos cursos.**

Versão **1.0.0-beta / 2026111001**. Moodle **5.1 e 5.2**. Componente **mod_lumen10voiceaccess**. Licença **GPL v3 ou posterior**.

Instale somente o ZIP `mod_lumen10voiceaccess-1.0.0-beta.zip`. O assistente usa hooks nativos para funcionar em todo o site, independentemente da criação de uma atividade. A atividade de curso oferece uma entrada conveniente, sem outro plugin obrigatório.

O antigo `local_lumen10_voiceaccess` foi incorporado. A atualização mantém as atividades existentes e migra tabelas, preferências, credenciais cifradas, autorizações, dispositivos e configurações institucionais. Há proteção contra tabelas conflitantes e transferência das permissões explícitas. A chave Gemini continua no servidor, no mesmo local protegido. Consulte [UPGRADING.md](UPGRADING.md) antes de retirar o componente antigo; não desinstale a atividade nem exclua suas tabelas.

Depois de instalar, configure o plugin em Administração do site > Plugins > Módulos de atividades. O analista gerencia autorizações em `/mod/lumen10voiceaccess/manage.php`; participantes autorizados usam `/mod/lumen10voiceaccess/preferences.php`. Gerentes não recebem acesso de participante automaticamente. Administradores têm acesso técnico para diagnóstico.

Os recursos incluem leitura de página e títulos, navegação por foco e comandos, ditado em campos permitidos, confirmação de ações, contraste e texto ampliado do painel, preferências e percurso de aprendizagem simplificado. Funcionamento por voz depende do dispositivo, navegador, HTTPS e permissão do microfone. Teclado e controles convencionais permanecem disponíveis.

IA é opcional e vem desligada em instalação nova. Reconhecimento de fala do navegador pode processar áudio externamente. A interpretação por IA pode enviar a frase e contexto limitado; Gemini Live recebe áudio e texto cuja leitura foi solicitada. O plugin não armazena áudio nem transcrições. A instituição define privacidade, consentimento, provedor, modelos, público e cotas. Limites locais não garantem teto financeiro.

A frase de entrada é uma credencial privada, não reconhecimento biométrico da pessoa. Há sessão obrigatória, limite de tentativas e respeito ao bloqueio de conta. Administradores e autenticação externa usam o acesso nativo. O auxiliar Windows é opcional, manual e restrito ao próprio site, sem alterar políticas ou instalar execução automática.

Guardian IA é outro produto e não integra este ZIP. Prova, Admissão e os demais componentes Lumen10 também são independentes.

Esta distribuição continua beta: os testes automatizados não substituem validação com pessoas, dispositivos e microfones reais nem significam aprovação do Moodle Marketplace. A [documentação em inglês](README.md) detalha instalação, operação, serviços externos e limites.

Autoria das contribuições originais: Carlos Antonio de Oliveira Piquet. A assinatura Ed25519 é técnica, não registro oficial no INPI, e preserva os direitos concedidos pela GPL.
