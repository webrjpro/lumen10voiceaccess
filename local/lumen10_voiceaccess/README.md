# Lumen10 Acesso Universal — 0.5.1-beta / 2026110300

Pacote de distribuição preparado em 03/11/2026, baseado no código 0.5.0-beta
conferido em produção. Compatibilidade declarada: Moodle 5.1 e 5.2. Consulte
[INSTALLATION.md](INSTALLATION.md) para instalação com a chave de IA do próprio cliente.
Esta revisão atualiza documentação e metadados, sem alterar a lógica da assistência.
Em instalações novas, a IA permanece desligada e sem credenciais. O padrão institucional é Gemini
3.8 Live para áudio e 3.5 Flash-Lite para interpretação; a instituição deve verificar
acesso e cotas efetivas do próprio projeto. O aluno não precisa de chave pessoal Gemini.

## Gemini institucional

A Gemini interpreta comandos por texto pela rota autenticada `interpret.php`.
A chave fica exclusivamente no servidor (`LUMEN10_GEMINI_API_KEY` ou
`$CFG->local_lumen10_voiceaccess_geminikey`). Também aceita um arquivo protegido em
`$CFG->dataroot/local_lumen10_voiceaccess/secrets/gemini.key`, ou o caminho indicado
por `LUMEN10_GEMINI_API_KEY_FILE`. O arquivo deve ficar fora da raiz pública, legível
somente pelo serviço Moodle e pelos responsáveis pelo servidor, sem entrar em ZIPs
do plugin ou no Git. A chave não deve ser copiada para este documento.
Em Moodle 5.1, confira qual configuração é carregada por `public/config.php`:
uma definição em outro `config.php` não terá efeito se esse arquivo não for incluído.

Gestores configuram a voz e autorizam pessoas em `manage.php`; essa tela mostra
somente o estado da Gemini. Alterações institucionais de IA e consumo exigem
`moodle/site:config`. Salvar a voz preserva a configuração de IA existente.
O limite compartilhado reserva cada tentativa antes da chamada externa, inclusive
testes administrativos, e contabiliza tentativas que falharam.

## Conversa natural com Gemini Live

Desabilitada por padrão. O administrador habilita `gemini_live_enabled` após homologação.
O público Live é um piloto independente, limitado aos nomes em `gemini_live_pilotusers`,
além de administradores em diagnóstico. Exige as autorizações existentes de acesso
e de Gemini. Essa lista não reduz o público que já utiliza o Flash-Lite.
No percurso habilitado, a conversa inicia automaticamente depois da permissão do
microfone. Alt+Shift+L e o botão mantêm o controle manual; não é obrigatória uma frase
de ativação. O navegador exige HTTPS (ou localhost) e permissão inicial
de microfone. A conversa mantém a captura contínua durante a sessão, sem apertar o
microfone a cada frase. Ao ativar, o áudio é enviado diretamente ao Google.

O endpoint autenticado `live.php` exige POST, sesskey e autorização individual; emite
um token de uso único, válido por 60 segundos para iniciar a sessão, com modelo,
voz, ferramentas e instruções fixados pelo servidor. A conexão usa `v1beta`,
`BidiGenerateContentConstrained` e `access_token`. A chave permanente não sai do servidor.
O REST de provisionamento usa `bidiGenerateContentSetup`; `liveConnectConstraints`
é o formato do SDK e não foi aceito no REST testado.

Áudio mono PCM16: captura a 16 kHz em AudioWorklet e reprodução a 24 kHz. A voz natural
vem do Live, com Sulafat por padrão, opções de voz, volume e velocidade de reprodução.
A troca de voz vale para a próxima sessão. Há corte local de reprodução e interrupção
pela detecção de atividade do Google. “Parar de ouvir” encerra o microfone; “modo local”
retorna aos comandos existentes. Falhas e cotas não desativam o modo local/Flash-Lite.

As ferramentas do Live aceitam somente intenções de leitura, navegação conhecida e
controle de mídia. Não recebem URLs, seletores, confirmação, submissão, edição ou
itens numerados que possam apontar a botões. Respostas de formulário e confirmações
continuam no mecanismo local. Dados de página só são enviados quando uma leitura
é solicitada. Mudar de aba interrompe a captura; a retomada entre páginas requer
continuação recente, mesma conta e a preferência de retomar habilitada.

Sessões duram de 60 a 300 segundos (padrão 180). A emissão consome as cotas compartilhadas
de tentativas do Flash-Lite e Live, reservadas antes da chamada externa. Contagens de
tokens Live são estimativas fornecidas pelo cliente, não prova contábil nem autorização
para novas despesas. O limite do navegador e a cota de tentativas não garantem custo
zero. O campo “modalidade” é declarado pelo administrador e não consulta o faturamento.
Não há ativação automática de faturamento, reconexão ilimitada ou retomada da sessão Google.

Desligar `gemini_live_enabled` bloqueia imediatamente novas emissões. Sessões abertas
verificam a autorização junto ao Moodle a cada 5 segundos, sem consultas sobrepostas,
e encerram se o servidor negar ou não responder em 4 segundos. Em condições normais,
isso representa até aproximadamente 9 segundos para encerrar uma sessão aberta;
não é revogação instantânea do token no Google. Ocultar a aba encerra a captura local.

O registro operacional persistente em `local_l10va_live` guarda hash do identificador,
titular, validade, motivo e contadores limitados. Não guarda áudio, transcrições,
conteúdo de páginas, URLs, IP, token de autorização ou chave. A retenção é de 30 dias,
executada pela tarefa horária e dependente do cron. Reprodução, trilhas de captura e
conexões são liberadas ao encerrar a sessão. Exportação e exclusão pessoal incluem o registro.

## Saúde operacional e capacidade

O padrão de `gemini_live_maxsessions` é dez autorizações lógicas simultâneas, com
reserva persistente sob bloqueio de projeto. Recusar por capacidade não consome
tentativa. O encerramento informado pelo cliente ou a expiração libera a vaga.
Não é inventário de conexões físicas no Google nem garantia contra clientes adulterados.

`gemini.php` e sua exportação JSON exigem `moodle/site:config`. O painel mostra
agregados de conexões, ações, falhas, tempos e tokens estimados, com alertas locais.
O CLI `php public/local/lumen10_voiceaccess/cli/health.php --hours=24` não chama o
Google; retorna 0 sem alertas ou 2 com alertas. Não envia notificações externas.

Uma falha de rede/socket permite até três reconexões por janela de dois minutos,
com esperas de aproximadamente 2, 5 e 10 segundos. Escape, parar explicitamente,
ocultar a aba e revogação cancelam a continuação. Cota, permissão e capacidade não
geram repetição automática. A apresentação não é repetida na renovação da mesma sessão.

A voz principal vem do Live. Voz do navegador é contingência para falta de internet
ou esgotamento de cota, conforme preferências e suporte. Não há fala Windows concorrente.
As falas fixas pré-gravadas são arquivos locais, sem TTS a cada reprodução.

Na rodada operacional: 91 testes JavaScript, 72 testes PHP / 332 asserções,
55 verificações de navegador, admissão em dez processos e áudio simulado em dez
navegadores; restauração de 669 tabelas sintéticas. Isso não certifica dez conversas
reais no Google, uso prolongado ou aceitação com pessoas e microfones reais.

Acesso global por voz e teclado para Moodle 5.1 ou superior. Autor: Carlos Antonio de Oliveira Piquet.
Licença GNU GPL v3 ou posterior. Não depende de tema, categoria ou outro plugin Lumen10.

## Instalação

1. Instale o ZIP local_lumen10_voiceaccess no instalador nativo. Pasta: public/local/lumen10_voiceaccess.
2. Opcional: instale mod_lumen10voiceaccess para aparecer em Adicionar uma atividade ou recurso.
3. Administração do site → Plugins locais → Lumen10 VoiceAccess.
4. Cada pessoa abre /local/lumen10_voiceaccess/index.php para configurar voz, perfil e IA.
5. O analista abre /local/lumen10_voiceaccess/manage.php e autoriza somente quem declarou cegueira ou mobilidade severamente reduzida e solicitou a assistência. Participantes precisam de autorização explícita. Administradores do site têm acesso técnico para diagnóstico.

O botão de gestão aparece depois de Desenvolvimento no menu lateral da administração Lumen10.
Em outros temas, há uma entrada nativa na administração de usuários. A capacidade
local/lumen10_voiceaccess:manage deve ser atribuída somente aos analistas responsáveis;
administradores e o papel gerente podem gerenciar autorizações; gerentes precisam de aprovação individual para usar o assistente. Administradores do site também podem testar o assistente para diagnóstico.
O analista não acessa a chave de IA individual por essa tela.

A página de preferências oferece “Verificar meu dispositivo”: consulta reconhecimento,
permissão de microfone e síntese de voz, sem iniciar captura nem fazer chamadas à IA.
Também informa pendências da configuração salva de IA, incluindo liberação institucional,
provedor, chave, consentimento e limite diário. Configuração preenchida não comprova
validade da chave, saldo ou conexão com o provedor. A amostra de voz informa término ou falha
em uma região acessível a leitores de tela.

A seleção de pessoas carrega imediatamente até 50 contas ativas cadastradas, com
autocompletar, total disponível e paginação. A busca aceita nome completo, nome de
usuário ou e-mail. Selecionar uma conta não a autoriza: a declaração e sua confirmação
continuam obrigatórias. Contas suspensas, excluídas e visitante não são oferecidas.

Em Moodle 5.1+, use o diretório public do seu código. Não copie arquivos para o núcleo.
Requer as dependências normais do Moodle 5.1 (inclusive Sodium para proteger chaves).

## O que funciona

- Painel em páginas HTML normais do Moodle, inclusive login padrão.
- Comandos em português e inglês; navegação global inclui menus e barras laterais.
- Leitura do texto visível, títulos, localização atual; foco e listagem numerada dos controles.
- Ditado em campos HTML comuns; campos protegidos e editores complexos são bloqueados.
- Alternativas de questões com confirmação, sem resolver as perguntas por IA.
- Seleção de opções, confirmação antes de clicar/salvar/enviar/limpar; expiração em 20 segundos.
- Varredura dos controles com F8 e Escape; não é um controlador de hardware de acionador único.
- Escolha das vozes do navegador/sistema, velocidade, tom e volume nas preferências.
- Seleção temporária de voz no painel público; “próxima voz”, “voz anterior”, “testar voz”.
- Configurações persistentes por conta; perfil leitor de tela evita síntese sobreposta.
- Alto contraste e texto maior do painel, sem alterar o conteúdo pedagógico.

## Ativação e privacidade do microfone no mecanismo local

Autorize o microfone e ligue-o pelo botão ou Alt+Shift+M. Então diga:
“Lumendez, ativar”, “Lumendez, ajuda”, “Lumendez, ler títulos”, “Lumendez, meus cursos”.
“Lumendez, desativar” pausa os comandos e mantém a escuta para reativar.
“Lumendez, parar de ouvir” ou Escape desliga o microfone; depois é necessário reativá-lo.
“Lumen” continua aceito como alias. Comandos digitados dispensam o prefixo e o estado de voz.
O prefixo obrigatório é configurável; a ativação é detectada pelo reconhecedor do navegador,
não por um detector acústico offline. Somente texto final é processado.

HTTPS (ou localhost) e suporte a SpeechRecognition são necessários. O navegador pode usar
um serviço externo para áudio e para algumas vozes. O plugin não grava áudio nem histórico
de frases e exige ativação inicial explícita. A opção “Retomar microfone nesta aba ao navegar”,
ou “Lumendez, ativar modo mãos livres”, permite tentar continuar a escuta na próxima página.
Guarda somente estado de escuta, modo de reconhecimento e identificador de conta nesta aba,
por até um minuto. Não guarda frases, áudio, chaves, sesskey ou ações pendentes. A troca de conta
e o logout impedem a retomada; Escape apaga o estado. Ao ocultar a aba, o áudio é interrompido.
A retomada depende da política do navegador, sem promessa de escuta contínua universal ou
de controle do primeiro diálogo de permissão pelo próprio site.
Leitores de tela e controles de acessibilidade do sistema podem acionar os controles iniciais.

O modo “Somente neste dispositivo” usa processLocally e exige implementação do navegador
e pacote de idioma compatível. A instalação do idioma é uma ação explícita nas preferências.
Não há mudança silenciosa para nuvem se esse modo falhar. Disponibilidade do idioma precisa
ser verificada no dispositivo real. Reconhecimento com baixa confiança não executa ações;
na conta com IA autorizada, pode pedir recuperação limitada. Reinícios e erros de rede
recebem espera e limite; falhas recorrentes desligam o microfone e mantêm alternativas locais.

## Login discreto

Nunca peça que uma pessoa dite sua senha em público. O plugin bloqueia o ditado de senhas,
não identifica pessoas pela voz e não fornece biometria vocal.
“Lumendez, entrar com segurança” ou “Lumendez, entrar com Google/Microsoft” encontra provedores
de identidade já apresentados pelo Moodle e pede confirmação antes de abrir o fluxo nativo.
O provedor deve estar configurado pela instituição. A autenticação externa continua no
provedor/dispositivo, onde passkeys ou biometria podem estar disponíveis conforme sua configuração.
Outra opção é preenchimento protegido pelo gerenciador de senhas do dispositivo, seguido de
“Lumendez, entrar” e confirmação. A ausência de provedor é informada, sem simular uma autenticação.
A WebAuthn do MFA do Moodle é um segundo fator; esta versão não a transforma em login por passkey.
Uma integração própria de primeiro fator com passkeys permanece trabalho futuro.
PIN, biometria e janelas nativas dependem de tecnologia assistiva do dispositivo; não se promete
login somente por fala em qualquer computador nem se salva senha de conta neste plugin.

O visitante comum e as contas fora da lista conservam o reCAPTCHA nativo e não recebem
o botão VoiceAccess. Configure as chaves e habilite o reCAPTCHA no Moodle para essa proteção.
Para a pessoa autorizada usar voz antes do login, o analista gera um vínculo privado de uso
único válido por uma hora e o entrega somente à pessoa/dispositivo correto. O segredo vai no
fragmento da URL, removido ao abrir; ele não é enviado em consultas de URL ou cabeçalhos Referer.
A confirmação grava um cookie HttpOnly/SameSite=Lax/Secure em HTTPS por até 90 dias; o banco
guarda somente hashes. Esse vínculo não autentica e não revela a conta ou a autodeclaração.
Revogar acesso ou dispositivos invalida os vínculos. A IA permanece inacessível antes do login.

Na entrada, a exceção de CAPTCHA exige dispositivo válido, autorização ativa e identificador
da mesma conta ao enviar o formulário. Outra conta no mesmo dispositivo ainda exige CAPTCHA.
Sem vínculo ou com vínculo revogado/vencido, o login volta ao comportamento nativo.
CAPTCHA de cadastro, logintoken, sessão, bloqueios de tentativas e MFA são preservados.
Uma aba aberta reconfere autorização ao voltar ao foco e a cada 30 segundos enquanto visível;
a revogação interrompe escuta/varredura/IA e remove o painel. A falha temporária de rede não é
interpretada como revogação; endpoints de preferências e IA sempre conferem a autorização.

## IA opcional: GPT-6 Luna

A IA vem desabilitada para a instituição. O administrador pode liberá-la; cada pessoa fornece
sua chave de API, seleciona provedor/modelo e consente explicitamente.
Na integração pessoal alternativa, o modelo OpenAI padrão é **gpt-6-luna**, com Responses API, reasoning.effort=none e store=false. Isso não substitui a Gemini institucional descrita acima.
Não há fallback automático para outro modelo ou outra chave.
Claude é opcional; informe seu identificador de modelo disponível no provedor.
Uma assinatura ChatGPT/Claude não equivale a crédito de API.

Comandos conhecidos são locais. Frases desconhecidas, reconhecimento de baixa confiança ou alvo
não encontrado podem acionar a recuperação autorizada por IA. O classificador pode enviar
o comando e um mapa limitado de nomes de cursos ao provedor. No Gemini Live, o áudio
vai ao Google e uma leitura solicitada pode enviar texto visível da página.
Não inclua dados pessoais nas frases; avalie os dados da página antes de solicitar leitura.
IA só pode escolher uma lista de intenções de leitura/navegação; não pode retornar seletores,
URLs executáveis, código, respostas acadêmicas, cliques, ditado ou confirmação.
Validação exige confiança ≥0,85 e descarta argumentos extras. Erros mantêm os comandos locais.
Endpoints fixos HTTPS e sem redirecionamento; chaves cifradas com core\\encryption e nunca
devolvidas ao navegador, backup de curso, exportação de privacidade ou logs do plugin.
A instituição deve proteger moodledata/secret junto com o banco nos backups e controlar acesso.
Há 40 tentativas por pessoa/dia UTC por padrão, separação mínima de três segundos e trava por
pessoa; falhas também consomem tentativas. Isso limita chamadas, não garante um teto monetário:
configure limites de gasto também no provedor. Nenhuma chave real acompanha o pacote.

A camada de recuperação não recebe callbacks de escrita e não repete cliques ou submissões.
Após três falhas consecutivas, pausa novas tentativas por 60 segundos. O cliente também valida
a lista de intenções e limita a espera. Se IA, internet ou provedor falharem, os comandos locais
continuam operáveis. Isso não garante a conclusão automática de uma tarefa com falha externa.

“Listar atividades”, “abrir atividade 3”, “abrir curso Nome” e “próxima atividade” usam
matrículas e modinfo do Moodle com a visibilidade da pessoa atual; não dependem de categoria.
Essa lista fica no navegador e não é anexada à interpretação externa.

## Limites e evolução

Versão beta. “Moodle 5.1+” é o requisito mínimo, não uma certificação de toda versão futura,
tema, atividade, plugin, navegador ou conformidade WCAG integral.
Iframes externos, PDFs sem texto, canvas, controles sem rótulos, menus do navegador, diálogos
de arquivos e editores ricos podem precisar de adaptadores ou ferramentas do sistema.
Não há STT Whisper separado nesta entrega. Um comando ambíguo nunca escolhe uma questão
ou controle por adivinhação. Links externos diretos são bloqueados; recursos podem usar suas
rotas Moodle nativas. Páginas incorporadas/popup/impressão não recebem o painel.

Antes de uma versão estável: ensaios com pessoas cegas e com mobilidade severa, NVDA/JAWS/VoiceOver,
microfones reais, Chrome/Edge/Safari, acionadores, adaptadores de atividades, auditoria de
autenticação e testes ponta a ponta com o provedor configurado. Nenhum prêmio é garantido.

## Desenvolvimento e documentação

Na raiz do workspace: python scripts/build_voiceaccess.py.
O build produz módulos AMD nomeados sem minificação e ZIPs separados, determinísticos, com SHA256.
Para preparar a chave: python scripts/sign_voiceaccess.py prepare.
Depois do build: python scripts/sign_voiceaccess.py sign.
Para conferir assinatura, pacotes e fontes: python scripts/sign_voiceaccess.py verify.
A chave privada fica em .runtime/voiceaccess/private, fora do Git e dos ZIPs. Preserve um
backup privado dela e uma cópia independente da chave pública. A assinatura não equivale
a identidade certificada ou registro no INPI. Consulte AUTHORS.md e PROVENANCE.md.
Validações e estado da homologação: todos-os-md/LUMEN10_VOICEACCESS.md no workspace.

Fontes:
- Moodle 5.1: https://moodledev.io/general/releases/5.1
- Hooks: https://moodledev.io/docs/5.1/apis/core/hooks
- GPT-6 Luna: https://developers.openai.com/api/docs/models/gpt-6-luna
- Responses: https://developers.openai.com/api/docs/guides/migrate-to-responses
- Claude: https://platform.claude.com/docs/en/api/overview
- Web Speech: https://developer.mozilla.org/en-US/docs/Web/API/SpeechRecognition
- Autenticação acessível: https://www.w3.org/WAI/WCAG22/Understanding/accessible-authentication-minimum.html
- WebAuthn: https://www.w3.org/TR/webauthn-2/

