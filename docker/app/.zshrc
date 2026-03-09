# PATH Configuration - MUST come BEFORE antigen
export PATH="/home/developer/.local/bin:$PATH"
export PATH="/home/developer/.config/composer/vendor/bin:$PATH"

source ~/.antigen/antigen.zsh

antigen use oh-my-zsh

# Core plugins
antigen bundle git
antigen bundle command-not-found
antigen bundle docker

# Syntax highlighting and autosuggestions
antigen bundle zsh-users/zsh-autosuggestions
antigen bundle zsh-users/zsh-syntax-highlighting

# Theme
antigen theme robbyrussell

antigen apply
