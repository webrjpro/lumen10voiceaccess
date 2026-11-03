# Lumen10 Acesso Universal

Assistência de acessibilidade por voz e teclado para **Moodle 5.1 e 5.2**.
Distribuição **beta**, preparada em 03/11/2026.

Copyright 2026 Carlos Antonio de Oliveira Piquet, quanto às contribuições originais.
Licença **GNU GPL v3 ou posterior**; cada componente inclui o texto da licença.

## Baixar e instalar

Os componentes têm instaladores separados. Não use o ZIP geral do repositório no
instalador de um único plugin Moodle.

| Ordem | Componente | Instalador |
| --- | --- | --- |
| 1 | Plugin principal: `local_lumen10_voiceaccess` 0.5.2-beta | [Baixar ZIP](packages/local_lumen10_voiceaccess-0.5.2-beta.zip) |
| 2, opcional | Atividade de curso: `mod_lumen10voiceaccess` 0.2.2-beta | [Baixar ZIP](packages/mod_lumen10voiceaccess-0.2.2-beta.zip) |

O plugin local contém a assistência por voz, as preferências e a integração com IA.
A atividade adiciona uma entrada em um curso e depende do plugin local. Instale
primeiro o plugin local e depois, se desejado, a atividade, pelo instalador nativo.

Fontes: [plugin local](local/lumen10_voiceaccess) e [atividade](mod/lumen10voiceaccess).
Guia completo: [instalação e chaves do cliente](local/lumen10_voiceaccess/INSTALLATION.md).

## Chaves e serviços de IA

**Nenhuma chave real de IA acompanha os pacotes.** Não há banco de produção,
Moodledata, credenciais de servidor, chave privada de assinatura ou configuração
dos participantes da Lumen10. A IA vem desligada em uma instalação nova.

Para conversa por voz Gemini Live, cada instituição usa sua própria conta e chave
Google Gemini. O administrador do servidor fornece a chave por variável de ambiente,
configuração privada do Moodle ou arquivo protegido fora da pasta pública. O aluno
não precisa fornecer chave pessoal Gemini nesse modo. O campo não fica na atividade
de curso. Ativação de IA, modelos, público, autorizações e consentimento são
configurados pela própria instituição.

Quando permitido, há também integração alternativa com chaves pessoais OpenAI ou
Anthropic nas preferências de cada pessoa. Essa interpretação de comandos é
separada da conversa institucional Gemini Live.

O consumo e eventuais cobranças são do projeto do cliente no provedor. Os limites
locais do plugin não garantem um teto monetário. Áudio, comandos e contexto de
navegação podem ser processados externamente conforme a opção habilitada. Leia
o guia antes de ativar esses serviços.

## Publicação no Moodle Marketplace

Consulte [MARKETPLACE.md](MARKETPLACE.md) para os campos e descrições de cada
componente. Cadastre primeiro o plugin local e depois a atividade opcional. Eles
precisam ser disponibilizados como componentes separados.

## Autoria e integridade

Os avisos autorais e a licença GPL foram preservados. Os arquivos em `packages`
incluem um manifesto SHA-256, a assinatura Ed25519 e a chave pública de verificação.
Veja [CERTIFICADO_INTEGRIDADE.md](CERTIFICADO_INTEGRIDADE.md).

A assinatura verifica a integridade dos arquivos em relação à chave técnica. Não
impede cópias, não é registro de autoria no INPI e não é certificado ICP-Brasil.
A distribuição GPL permite uso, estudo, modificação e redistribuição conforme seus
termos. Não há bloqueio remoto, licença comercial oculta ou chave do autor embutida.

## Correction and validation

0.5.2-beta corrects the abbreviated database table prefixes rejected by
moodle-plugin-ci. The five tables and their PHP references now use the full component
prefix. Existing installations receive a native XMLDB rename migration that preserves
IDs and records rather than reinstalling or copying personal data.

The original 0.5.1-beta package reproduces the reported validation failure with the
official moodle-plugin-ci tool. The corrected package passes that validation and PHP
lint on PHP 8.3.36. Native Moodle tests cover the plugin and data-preserving migration;
consult [the executed checks](packages/qa-results.json) for exact results and limits.
Production was not upgraded. This remains beta, without a new real-provider,
load, device or WCAG certification.
