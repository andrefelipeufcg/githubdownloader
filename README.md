# GitHub Downloader para GLPI

Um plugin prático para GLPI que permite baixar, instalar e atualizar outros plugins diretamente de repositórios do GitHub através da interface web, eliminando a necessidade de usar o terminal (SSH) do servidor.

## 🚀 Funcionalidades

- **Download Direto:** Baixe a última *release* de qualquer repositório público do GitHub informando apenas a URL.
- **Extração Automática:** O plugin extrai o arquivo `.tar.gz` de forma nativa e posiciona o código no diretório correto.
- **Backup de Segurança:** Ao atualizar um plugin já existente, ele renomeia a versão atual para `[nome-do-plugin]-old` de forma automática, garantindo um backup de reversão.
- **Validação de Erros:** Checagem da existência do repositório, validação de permissões de pasta e tratamento seguro durante o processo de extração.

## 📋 Pré-requisitos

Para que o plugin funcione corretamente, o seu ambiente GLPI deve atender aos seguintes requisitos:

- **GLPI:** Versão 10.0.0 ou superior.
- **Permissão de Escrita:** O usuário do seu servidor web (ex: `www-data`, `apache` ou `nginx`) deve ter **permissão de escrita** no diretório genérico de plugins (`/var/www/html/glpi/plugins/` ou equivalente).
- **Extensões PHP:**
  - `curl` (Para conectar na API do GitHub)
  - `zlib` e `phar` (Para extrair os arquivos comprimidos `.tar.gz` sem precisar de ferramentas no sistema operacional)

## 🛠️ Instalação

1. Baixe o código fonte ou faça um clone deste repositório dentro da pasta `plugins/` do seu GLPI:
   ```bash
   cd /caminho/do/seu/glpi/plugins
   git clone https://github.com/andrefelipeufcg/githubdownloader.git
   ```
2. Acesse o GLPI usando uma conta de administrador.
3. Navegue até o menu **Configurar > Plugins**.
4. Localize o **GitHub Downloader** na lista e clique no botão para **Instalar** e depois **Ativar**.

## 💻 Como Usar

1. No GLPI, acesse **Configurar > Plugins**.
2. Clique no nome do plugin **GitHub Downloader** (isso abrirá a página de interface do plugin).
3. No campo "URL do Repositório", cole o link completo do GitHub do plugin desejado.
   - *Exemplo:* `https://github.com/ufcg/nomedoplugin`
4. Clique em **Baixar e Instalar**.
5. Aguarde alguns segundos. O plugin mostrará uma mensagem verde de sucesso no topo confirmando a instalação.
6. Volte para a tela de Plugins para ativar o plugin recém-baixado!

## ⚠️ Limitações Conhecidas

- Funciona apenas para repositórios **públicos** no GitHub (pois a API é consultada de forma anônima).
- O repositório destino **deve possuir** arquivos publicados via funcionalidade de *Releases* do GitHub.
- Para plugins de grande volume (muitos megabytes), verifique o `max_execution_time` do seu PHP para evitar *timeouts* durante o download e a extração.

## 📝 Licença

Este projeto é distribuído sob os termos da licença [GPLv3+](https://www.gnu.org/licenses/gpl-3.0.html).