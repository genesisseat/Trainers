# Workstation and Project Specification

## Hardware
- Laptop model: Gigabyte A16 CMH
- CPU: Intel Core i5-13420H
- Memory: 16 GB DDR5-5200
- GPU: NVIDIA RTX 4050 6GB GDDR6
- Storage: Windows system drive plus project work folders on local storage

## Workplace / directory layout
- Main workspace root: `W:\Trainers`
- Project folders:
  - `W:\Trainers\embedding-matcher`
  - `W:\Trainers\curriculum-generator-kb`
  - `W:\Trainers\curriculum-generator-project`
  - `W:\Trainers\Models`
- Current project folder for embedding work:
  - `W:\Trainers\embedding-matcher`
- Knowledge base data source:
  - `W:\Trainers\curriculum-generator-kb\data\curriculum_dataset_with_ids.csv`
  - `W:\Trainers\curriculum-generator-kb\03_industry_skills_data.md`
- Generated output files:
  - `W:\Trainers\embedding-matcher\course_to_skill_matches.csv`
  - `W:\Trainers\embedding-matcher\skill_coverage.csv`
  - `W:\Trainers\embedding-matcher\AI_CONTEXT.md`

## Installed services and environment
- Operating system: Windows 11
- PowerShell available for command execution
- Python 3.12.10
- Virtual environment created in project folder:
  - `W:\Trainers\embedding-matcher\venv`
- CUDA available for PyTorch:
  - `torch.cuda.is_available() == True`
- Hugging Face cache configured for model downloads:
  - `W:\Trainers\embedding-matcher\hf_cache`
- Python tooling installed in the venv:
  - `torch` (CUDA-enabled PyTorch)
  - `sentence-transformers`
  - `pandas`
  - `transformers`
  - `huggingface-hub`
  - `numpy`, `scikit-learn`, `scipy`

## Required software for this task
1. Python 3.10-3.12
2. NVIDIA driver compatible with RTX 4050
3. Windows PowerShell or equivalent terminal
4. Virtual environment support
5. CUDA-enabled PyTorch wheel
6. `sentence-transformers`
7. `pandas`
8. Optional but useful: Git, VS Code, and a local Hugging Face cache directory

## Installation commands used
```powershell
cd W:\Trainers\embedding-matcher
python -m venv venv
.\venv\Scripts\activate
pip install torch --index-url https://download.pytorch.org/whl/cu124
pip install sentence-transformers pandas
```

## GPU validation command
```powershell
python -c "import torch; print(torch.cuda.is_available())"
```
Expected result:
```text
True
```

## Project purpose
This workstation is used to evaluate curriculum-to-skill alignment by embedding:
- course records from the curriculum CSV
- skill records from the industry markdown dataset

The matcher computes similarity between course and skill text using pretrained sentence embeddings and identifies likely curriculum gaps.

## Main working script
- `W:\Trainers\embedding-matcher\match_courses_to_skills.py`

## Typical execution commands
```powershell
cd W:\Trainers\embedding-matcher
.\venv\Scripts\activate
python match_courses_to_skills.py --model all-MiniLM-L6-v2
python match_courses_to_skills.py --model BAAI/bge-small-en-v1.5
python match_courses_to_skills.py --model BAAI/bge-base-en-v1.5
```

## Notes
- No FAISS or vector database is required for this data size.
- The system is already successfully running the embedding matcher with CUDA available.
- The generated `skill_coverage.csv` is the primary file to review first for curriculum gaps.
