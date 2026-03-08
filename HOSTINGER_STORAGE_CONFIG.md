# 🔧 Configuração do Storage no Hostinger

## 📋 Problema

No Hostinger, o symlink `storage` aponta para `storage/public/` em vez de `storage/app/public/`.

## ✅ Solução (2 Opções)

---

## **Opção 1: Configurar .env no Hostinger (Recomendado)**

### Passo 1: Descubra o Caminho Real

Via SSH no Hostinger:

```bash
# Conecte no SSH
ssh u123456789@hostinger.com -p 65002

# Navegue até o projeto
cd ~/domains/education.medtrack.click/public_html

# Veja para onde o symlink aponta
readlink -f storage

# Exemplo de resultado:
# /home/u123456789/domains/education.medtrack.click/storage/public
```

### Passo 2: Configure o .env de Produção

No arquivo `.env` **do Hostinger** (não o local), adicione:

```env
# Configuração do Storage para Hostinger
PUBLIC_STORAGE_ROOT=/home/u123456789/domains/education.medtrack.click/storage/public

# Exemplo completo:
APP_URL=https://education.medtrack.click
PUBLIC_STORAGE_ROOT=/home/u123456789/domains/education.medtrack.click/storage/public
```

**⚠️ Substitua pelo caminho real do seu servidor!**

### Passo 3: Crie a Estrutura de Pastas

Via SSH:

```bash
# Navegue até o storage público
cd ~/domains/education.medtrack.click/storage/public

# Crie as pastas necessárias
mkdir -p audio/sentences

# Configure permissões
chmod -R 775 audio

# Verifique
ls -la audio/
```

### Passo 4: Limpe os Caches

```bash
cd ~/domains/education.medtrack.click/public_html
php artisan config:clear
php artisan cache:clear
```

### Passo 5: Teste

```bash
# Crie um arquivo de teste
echo "teste" > ~/domains/education.medtrack.click/storage/public/audio/test.txt

# Acesse no navegador:
# https://education.medtrack.click/storage/audio/test.txt
```

Se aparecer "teste", está funcionando! ✅

---

## **Opção 2: Recriar o Symlink Padrão**

Se preferir usar a estrutura padrão do Laravel:

### Via SSH:

```bash
cd ~/domains/education.medtrack.click/public_html

# Remove o symlink atual
rm -f public/storage

# Cria o symlink padrão do Laravel
ln -s ../storage/app/public public/storage

# Cria as pastas necessárias
mkdir -p storage/app/public/audio/sentences
chmod -R 775 storage/app/public

# Verifica
ls -la public/storage
```

### Não precisa alterar .env

Com essa opção, o Laravel usa o caminho padrão:
- `storage/app/public/` (onde os arquivos são salvos)
- `public/storage/` (symlink para acesso web)

---

## 🔍 Como Saber Qual Usar?

Execute via SSH:

```bash
cd ~/domains/education.medtrack.click/public_html
ls -la public/storage
```

**Se mostrar:**
```
storage -> ../storage/public
```
→ Use **Opção 1** (configurar .env)

**Se mostrar:**
```
storage -> ../storage/app/public
```
→ Não precisa fazer nada! Já está correto.

---

## 📊 Estrutura Esperada

### Opção 1 (Storage customizado):
```
/home/u123456789/domains/education.medtrack.click/
├── public_html/
│   └── public/
│       └── storage → ../../storage/public  ✅
└── storage/
    └── public/
        └── audio/
            └── sentences/
                └── exercise-1.mp3
```

**URL:** `https://education.medtrack.click/storage/audio/sentences/exercise-1.mp3`

### Opção 2 (Padrão Laravel):
```
/home/u123456789/domains/education.medtrack.click/public_html/
├── public/
│   └── storage → ../storage/app/public  ✅
└── storage/
    └── app/
        └── public/
            └── audio/
                └── sentences/
                    └── exercise-1.mp3
```

**URL:** `https://education.medtrack.click/storage/audio/sentences/exercise-1.mp3`

---

## ✅ Verificação Final

Após configurar, teste via SSH:

```bash
# 1. Verifique o caminho do disco 'public'
cd ~/domains/education.medtrack.click/public_html
php artisan tinker
>>> Storage::disk('public')->path('audio/sentences')

# 2. Liste os arquivos
>>> Storage::disk('public')->files('audio/sentences')

# 3. Crie um teste
>>> Storage::disk('public')->put('audio/test.txt', 'funciona!')

# 4. Acesse no navegador
# https://education.medtrack.click/storage/audio/test.txt
```

---

## 🚀 Deploy Checklist

- [ ] Código atualizado com `PUBLIC_STORAGE_ROOT` em `config/filesystems.php`
- [ ] Descoberto o caminho real no Hostinger
- [ ] Configurado `.env` de produção com `PUBLIC_STORAGE_ROOT`
- [ ] Criadas pastas `audio/sentences/`
- [ ] Configuradas permissões `chmod -R 775`
- [ ] Limpo cache: `php artisan config:clear`
- [ ] Testado arquivo: `storage/audio/test.txt`
- [ ] Verificado URL no navegador

---

## 🆘 Troubleshooting

### Erro 404 nos áudios

```bash
# Verifique se as pastas existem
ls -la $(readlink -f public/storage)/audio/sentences/

# Verifique permissões
chmod -R 775 $(readlink -f public/storage)/audio
```

### Erro "Directory not writable"

```bash
# Ajuste o dono
chown -R seu_usuario:seu_usuario storage
chmod -R 775 storage
```

### Arquivos não aparecem

```bash
# Verifique onde o Laravel está salvando
php artisan tinker
>>> Storage::disk('public')->path('audio/sentences')

# Compare com onde o symlink aponta
readlink -f public/storage
```

---

**Escolha a Opção 1 ou 2 e siga os passos!** ✅
