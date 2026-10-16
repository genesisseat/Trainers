"""Shared runtime path and Hugging Face cache configuration."""

from __future__ import annotations

import os
from pathlib import Path


HF_CACHE_VARIABLES = (
    "HF_HOME",
    "HF_HUB_CACHE",
    "HUGGINGFACE_HUB_CACHE",
    "TRANSFORMERS_CACHE",
    "SENTENCE_TRANSFORMERS_HOME",
    "HF_DATASETS_CACHE",
)


def knowledge_base_dir(project_root: Path | None = None) -> Path:
    """Return the configured knowledge-base directory or its sibling default."""
    project_root = (project_root or Path(__file__).resolve().parent).resolve()
    configured_dir = os.environ.get("KB_DIR", "").strip()
    if configured_dir:
        configured_path = Path(configured_dir).expanduser()
        if not configured_path.is_absolute():
            configured_path = project_root.parent / configured_path
        return configured_path.resolve()
    return project_root.parent / "curriculum-generator-kb"


def configure_huggingface_cache(project_root: Path | None = None) -> Path:
    """Use external cache settings untouched, or configure the local fallback."""
    project_root = (project_root or Path(__file__).resolve().parent).resolve()
    configured_cache = any(os.environ.get(name, "").strip() for name in HF_CACHE_VARIABLES)
    if configured_cache:
        configured_home = os.environ.get("HF_HOME", "").strip()
        if configured_home:
            return Path(configured_home).expanduser()
        for name in HF_CACHE_VARIABLES[1:]:
            configured_path = os.environ.get(name, "").strip()
            if configured_path:
                return Path(configured_path).expanduser()
        return project_root / "hf_cache"

    local_cache = project_root / "hf_cache"
    local_cache.mkdir(parents=True, exist_ok=True)
    os.environ["HF_HOME"] = str(local_cache)
    return local_cache
