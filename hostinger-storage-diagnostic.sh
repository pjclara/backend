#!/bin/bash

# =============================================================================
# 🔍 Diagnóstico de Storage no Hostinger
# Execute este script via SSH para descobrir a configuração correta
# =============================================================================

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║       🔍 Diagnóstico de Storage - Hostinger                    ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Cores
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# =============================================================================
# 1. LOCALIZAÇÃO DO PROJETO
# =============================================================================
echo -e "${BLUE}📍 1. Localizando projeto Laravel...${NC}"
echo ""

if [ -f "artisan" ]; then
    PROJECT_ROOT=$(pwd)
    echo -e "${GREEN}✅ Projeto encontrado em:${NC} $PROJECT_ROOT"
else
    echo -e "${RED}❌ Não está na raiz do projeto Laravel!${NC}"
    echo "   Execute: cd ~/domains/seu-dominio/public_html"
    exit 1
fi

echo ""

# =============================================================================
# 2. VERIFICAR SYMLINK
# =============================================================================
echo -e "${BLUE}🔗 2. Verificando symlink do storage...${NC}"
echo ""

if [ -L "public/storage" ]; then
    SYMLINK_TARGET=$(readlink public/storage)
    REAL_PATH=$(readlink -f public/storage)
    
    echo -e "${GREEN}✅ Symlink existe:${NC}"
    echo "   Link: public/storage"
    echo "   Aponta para: $SYMLINK_TARGET"
    echo "   Caminho real: $REAL_PATH"
    echo ""
    
    # Determinar tipo de estrutura
    if [[ "$SYMLINK_TARGET" == *"app/public"* ]]; then
        STORAGE_TYPE="padrao"
        echo -e "${GREEN}📂 Tipo: Estrutura Padrão do Laravel${NC}"
        echo "   storage/app/public/"
    else
        STORAGE_TYPE="customizado"
        echo -e "${YELLOW}📂 Tipo: Estrutura Customizada do Hostinger${NC}"
        echo "   storage/public/"
    fi
else
    echo -e "${RED}❌ Symlink NÃO existe!${NC}"
    echo "   Você precisa criar o symlink primeiro."
    STORAGE_TYPE="nenhum"
fi

echo ""

# =============================================================================
# 3. VERIFICAR PASTAS DE ÁUDIO
# =============================================================================
echo -e "${BLUE}🎵 3. Verificando pastas de áudio...${NC}"
echo ""

if [ "$STORAGE_TYPE" == "padrao" ]; then
    AUDIO_PATH="storage/app/public/audio/sentences"
elif [ "$STORAGE_TYPE" == "customizado" ]; then
    AUDIO_PATH="$REAL_PATH/audio/sentences"
else
    AUDIO_PATH=""
fi

if [ -n "$AUDIO_PATH" ] && [ -d "$AUDIO_PATH" ]; then
    echo -e "${GREEN}✅ Pasta de áudio existe:${NC} $AUDIO_PATH"
    
    # Contar arquivos
    FILE_COUNT=$(find "$AUDIO_PATH" -name "*.mp3" | wc -l)
    echo "   Arquivos MP3: $FILE_COUNT"
    
    # Verificar permissões
    PERMS=$(stat -c "%a" "$AUDIO_PATH" 2>/dev/null || stat -f "%Lp" "$AUDIO_PATH")
    echo "   Permissões: $PERMS"
    
    if [ "$PERMS" -ge "755" ]; then
        echo -e "   ${GREEN}✅ Permissões OK${NC}"
    else
        echo -e "   ${YELLOW}⚠️  Permissões podem estar incorretas${NC}"
    fi
else
    echo -e "${RED}❌ Pasta de áudio NÃO existe!${NC}"
    echo "   Esperado: $AUDIO_PATH"
fi

echo ""

# =============================================================================
# 4. GERAR CONFIGURAÇÃO DO .ENV
# =============================================================================
echo -e "${BLUE}⚙️  4. Configuração recomendada para .env${NC}"
echo ""

if [ "$STORAGE_TYPE" == "padrao" ]; then
    echo -e "${GREEN}✅ Usando estrutura padrão do Laravel${NC}"
    echo ""
    echo "Adicione ao .env:"
    echo "─────────────────────────────────────────────────────────"
    echo "APP_URL=https://$(hostname | cut -d'.' -f2-)"
    echo "# Não precisa de PUBLIC_STORAGE_ROOT"
    echo "─────────────────────────────────────────────────────────"
    
elif [ "$STORAGE_TYPE" == "customizado" ]; then
    echo -e "${YELLOW}⚠️  Usando estrutura customizada${NC}"
    echo ""
    echo "Adicione ao .env:"
    echo "─────────────────────────────────────────────────────────"
    echo "APP_URL=https://$(hostname | cut -d'.' -f2-)"
    echo "PUBLIC_STORAGE_ROOT=$REAL_PATH"
    echo "─────────────────────────────────────────────────────────"
    
else
    echo -e "${RED}❌ Symlink não configurado${NC}"
    echo ""
    echo "Execute um dos comandos:"
    echo ""
    echo "Opção 1 - Estrutura Padrão (Recomendado):"
    echo "─────────────────────────────────────────────────────────"
    echo "ln -s ../storage/app/public public/storage"
    echo "mkdir -p storage/app/public/audio/sentences"
    echo "chmod -R 775 storage/app/public"
    echo "─────────────────────────────────────────────────────────"
    echo ""
    echo "Opção 2 - Estrutura Hostinger:"
    echo "─────────────────────────────────────────────────────────"
    echo "ln -s ../../storage/public public/storage"
    echo "mkdir -p ../storage/public/audio/sentences"
    echo "chmod -R 775 ../storage/public"
    echo "─────────────────────────────────────────────────────────"
fi

echo ""

# =============================================================================
# 5. COMANDOS DE TESTE
# =============================================================================
echo -e "${BLUE}🧪 5. Comandos de teste${NC}"
echo ""

echo "Teste 1 - Criar arquivo de teste:"
echo "─────────────────────────────────────────────────────────"
if [ "$STORAGE_TYPE" == "padrao" ]; then
    echo "echo 'teste' > storage/app/public/audio/test.txt"
elif [ "$STORAGE_TYPE" == "customizado" ]; then
    echo "echo 'teste' > $REAL_PATH/audio/test.txt"
fi
echo "─────────────────────────────────────────────────────────"
echo ""

echo "Teste 2 - Acessar no navegador:"
echo "─────────────────────────────────────────────────────────"
echo "https://seu-dominio.com/storage/audio/test.txt"
echo "─────────────────────────────────────────────────────────"
echo ""

echo "Teste 3 - Via PHP Artisan:"
echo "─────────────────────────────────────────────────────────"
echo "php artisan tinker"
echo ">>> Storage::disk('public')->put('audio/test.txt', 'funciona!')"
echo ">>> Storage::disk('public')->path('audio/test.txt')"
echo "─────────────────────────────────────────────────────────"
echo ""

# =============================================================================
# 6. RESUMO E PRÓXIMOS PASSOS
# =============================================================================
echo "═══════════════════════════════════════════════════════════════"
echo -e "${BLUE}📋 RESUMO${NC}"
echo "═══════════════════════════════════════════════════════════════"
echo ""

if [ "$STORAGE_TYPE" == "padrao" ]; then
    echo -e "${GREEN}✅ Estrutura Padrão do Laravel detectada${NC}"
    echo ""
    echo "Próximos passos:"
    echo "1. ✅ Symlink está correto"
    echo "2. Configure APP_URL no .env"
    echo "3. Execute: php artisan config:clear"
    echo "4. Teste: Criar arquivo e acessar via URL"
    
elif [ "$STORAGE_TYPE" == "customizado" ]; then
    echo -e "${YELLOW}⚠️  Estrutura Customizada do Hostinger detectada${NC}"
    echo ""
    echo "Próximos passos:"
    echo "1. Adicione PUBLIC_STORAGE_ROOT ao .env (veja acima)"
    echo "2. Execute: php artisan config:clear"
    echo "3. Crie as pastas: mkdir -p $REAL_PATH/audio/sentences"
    echo "4. Ajuste permissões: chmod -R 775 $REAL_PATH/audio"
    echo "5. Teste: Criar arquivo e acessar via URL"
    
else
    echo -e "${RED}❌ Symlink não configurado${NC}"
    echo ""
    echo "Próximos passos:"
    echo "1. Escolha uma opção de estrutura (veja comandos acima)"
    echo "2. Execute os comandos para criar o symlink"
    echo "3. Execute este script novamente"
    echo "4. Configure o .env conforme recomendado"
fi

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo ""
echo "📖 Para mais detalhes, veja: HOSTINGER_STORAGE_CONFIG.md"
echo ""
