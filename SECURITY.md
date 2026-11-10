# Segurança e relato de falhas

Esta é uma versão beta, sem promessa de ausência absoluta de falhas.
Não divulgue chaves, CPF, senhas, áudio, conteúdo de provas ou dados de alunos em issues.
O mantenedor ainda não informou um canal público de segurança; combine um canal privado
verificado com o responsável antes de transmitir detalhes exploráveis.

## Regras implementadas

- Chaves individuais cifradas pelo Moodle e ausência de credenciais em curso/backup/JS.
- Participantes exigem autorização individual e capacidade para usar IA. Administradores do site também têm acesso técnico; gerentes não recebem esse acesso automático.
- POST autenticado com sesskey; nenhuma API pública de IA na entrada.
- Endpoints de provedores fixos, HTTPS verificado, redirecionamentos desabilitados.
- Limites persistentes de tentativas, trava por pessoa e espera entre chamadas.
- IA com lista fechada de comandos de leitura/navegação e pausa após falhas repetidas.
- Confirmação expira e se vincula ao controle original; nunca repete uma entrega automaticamente.
- Senhas e campos sensíveis bloqueados no ditado; voz não autentica a identidade.
- Continuação opcional por aba, curta, sem frases ou credenciais e com isolamento de conta.
- Exceção de CAPTCHA vinculada ao dispositivo aprovado e ao identificador da mesma conta; as demais pessoas conservam o CAPTCHA nativo.
- Vínculos de uso único, com prazo, hash, trava nativa por pessoa e revogação; cookie HttpOnly/Secure em HTTPS/SameSite=Lax.
- Autodeclaração mínima restrita a analistas; sem laudos ou documentos médicos. Revogação e privacidade removem dispositivos.
- Verificação de integridade executável; assinatura não substitui teste de segurança.
- Verificação periódica do dono da sessão remove o painel de uma aba antiga após logout ou troca de conta.
- Exclusão de dados e gravação de preferências usam a mesma trava, impedindo recriação por formulário antigo.
- Respostas inválidas do provedor são recusadas; sequências reconhecíveis como chave de API são bloqueadas antes da recuperação por IA.
- A resolução de identidade para a exceção de CAPTCHA segue a prioridade nativa de nome de usuário antes de e-mail.

## Implantação

Mantenha HTTPS, backups do banco e moodledata/secret, bloqueio de tentativas do Moodle,
privilégios mínimos e limites de gasto no provedor. Valide o ambiente e dispositivos usados.
Não desabilite MFA nem compartilhe chaves de IA para resolver problemas de acessibilidade.
Em caso de comportamento inesperado, interrompa o microfone com Escape e preserve o trabalho.
Uma falha no plugin deve ser investigada antes de retomar ações acadêmicas sensíveis.

## Gemini institucional

A credencial pode vir do ambiente, de configuração protegida ou de arquivo privado fora
da raiz pública. A gestão de autorizações não permite ler ou substituir esse arquivo.
A reserva de cota global usa trava compartilhada antes da rede; falhas também consomem
a reserva. Alterações institucionais de IA e limites exigem `moodle/site:config`.

## Gemini Live (candidato 0.4)

- Emissão autenticada por POST, sesskey, consentimento e autorização de uso vigente.
- Token de uso único, janela de início de 60 segundos e configuração completa fixada
  no servidor. Endpoint WebSocket específico para tokens temporários, versão v1beta.
- A chave principal não participa do tráfego do navegador. Tokens temporários ficam
  apenas em memória e no handshake TLS com o Google, nunca em armazenamento ou logs.
- O navegador recebe somente intenções conhecidas; não aceita URL, seletor, código,
  edição, submissão, confirmação nem ativação genérica de itens numerados pelo modelo.
- Chamadas duplicadas ou canceladas não repetem ações. Lotes e frequência são limitados.
- Encerrar, ocultar a aba ou perder a conexão libera áudio, tracks e WebSocket. Cancelar
  a conexão não reativa o microfone por um retorno assíncrono posterior.
- Falhas retornam mensagens genéricas sem corpo do provedor, tokens ou credenciais.
- Cotas de emissão não são limites financeiros de áudio; contagens enviadas pelo
  navegador são estimativas. Faturamento é administrado diretamente no Google.
- Cache temporário de sessão com titular, validade e contadores; não guarda áudio,
  transcrições nem credenciais. Retomada do Google não é habilitada.
- Público piloto Live separado do Flash-Lite, vazio por padrão; acesso existente
  e autorização individual continuam obrigatórios. Administradores têm diagnóstico.
- Aberturas são bloqueadas ao desligar Live. Sessões ativas consultam a autorização
  a cada 5 segundos e fecham em caso de recusa ou timeout de 4 segundos, sem reconectar
  automaticamente. Não representa revogação instantânea de tokens pelo Google.
