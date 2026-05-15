# QR Code Modal - Implementação Completa

## 🎯 O que foi alterado

O sistema agora exibe um **modal elegante** logo após criar uma nova promoção, semelhante ao modal de recibo no CAIXA.

### Fluxo:
1. **Criar Promoção** → Preenche formulário e clica "Criar"
2. **QR Code Gerado** → Sistema gera automaticamente
3. **Modal Exibido** → Apareça popup com:
   - ✅ Confirmação de sucesso
   - 📱 QR Code em tamanho grande (300x300px)
   - 📋 Informações da promoção (nome + desconto)
   - 3 botões de ação:
     - Fechar (volta para lista)
     - ⬇️ Descarregar (salva como PNG)
     - 🖨️ Imprimir (abre janela de impressão)

## 🎨 Interface

```
┌─────────────────────────────────────────────────────┐
│                 ✅ Promoção Criada!                 │
│                                                     │
│           ┌───────────────────────────┐             │
│           │                           │             │
│           │      [QR CODE 300x300]    │             │
│           │                           │             │
│           └───────────────────────────┘             │
│                                                     │
│   Promoção: Black Friday -20%                       │
│   Desconto: -€20.00                                 │
│                                                     │
│   ┌──────────┐ ┌──────────┐ ┌──────────┐           │
│   │  Fechar  │ │⬇️Descarr. │ │🖨️Imprimir│           │
│   └──────────┘ └──────────┘ └──────────┘           │
└─────────────────────────────────────────────────────┘
```

## 💾 Alterações Técnicas

### Arquivo: `modules/promocoes.php`

**1. Adicionada variável para QR modal:**
```php
$qr_code_modal = null;  // Para armazenar dados do QR code a mostrar
```

**2. Ao criar promoção:**
- Sistema gera QR code
- Carrega dados da promoção criada
- Armazena em `$qr_code_modal`

**3. Modal CSS:**
- `.modal-qr-overlay` - Fundo escuro
- `.modal-qr-box` - Caixa do modal
- Animação de entrada (slideIn)
- Responsivo (90% width em mobile)

**4. Funções JavaScript:**
- `closeQrModal()` - Fecha o modal
- `downloadQr()` - Descarrega QR code como PNG
- `printQr()` - Abre janela de impressão formatada

## 🚀 Como Usar

### Criar Promoção com QR Code:
1. Acesse: `http://localhost:8000/modules/promocoes.php`
2. Preencha o formulário:
   - Nome: "Black Friday"
   - Tipo: "Percentagem"
   - Valor: "20"
3. Clique em "🏷️ Criar Promoção"
4. **Modal aparece automaticamente** com:
   - QR Code gerado
   - Opções de descarregar/imprimir

### Descarregar QR Code:
- Clique em "⬇️ Descarregar"
- Ficheiro é salvo como `qr-code-[id].png`

### Imprimir QR Code:
- Clique em "🖨️ Imprimir"
- Abre janela de impressão
- Pode ajustar tamanho/zoom
- Clique em "Imprimir"

## 🎯 Casos de Uso

✅ **Marketing**: Imprimir QR code para cartazes  
✅ **Digital**: Compartilhar QR code em redes sociais  
✅ **Recibos**: Incluir QR code em notas fiscais  
✅ **Campanhas**: Diferentes promoções com seus próprios QR codes  

## 📊 Comparação com Recibo

**Recibo no CAIXA:**
- Exibe após venda bem-sucedida
- Mostra valores da transação
- Botões: Fechar, Imprimir

**QR Code na Promoção:**
- Exibe após criar promoção
- Mostra nome e desconto
- Botões: Fechar, Descarregar, Imprimir

Ambos usam a mesma estrutura de modal para **consistência visual**.

## ✨ Características

- 🎨 Design responsivo e moderno
- 🌙 Suporta tema claro/escuro
- 📱 Totalmente mobile-friendly
- ⚡ Animação suave de entrada
- 🖨️ Impressão formatada
- 💾 Download de imagem
- ✅ Sem dependências externas

## 🔄 Próximos Passos (Opcional)

1. Compartilhamento direto em WhatsApp/Email
2. Histórico de QR codes gerados
3. Template customizável para impressão
4. Analytics: Quantas vezes QR foi escaneado
