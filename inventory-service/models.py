from flask_sqlalchemy import SQLAlchemy
from datetime import datetime

db = SQLAlchemy()

class Inventory(db.Model):
    __tablename__ = 'inventories'
    
    id = db.Column(db.Integer, primary_key=True, autoincrement=True)
    item_name = db.Column(db.String(255), nullable=False)
    stock = db.Column(db.Integer, default=0)
    unit = db.Column(db.String(50), nullable=False)
    status = db.Column(db.String(50), default='available')
    created_at = db.Column(db.DateTime, default=datetime.utcnow)

    def to_dict(self):
        return {
            'id': self.id,
            'item_name': self.item_name,
            'stock': self.stock,
            'unit': self.unit,
            'status': self.status,
            'created_at': self.created_at.isoformat() if self.created_at else None
        }
