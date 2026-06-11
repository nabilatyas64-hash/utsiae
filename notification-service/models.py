from flask_sqlalchemy import SQLAlchemy
from datetime import datetime

db = SQLAlchemy()

class Notification(db.Model):
    __tablename__ = 'notifications'
    
    id = db.Column(db.Integer, primary_key=True, autoincrement=True)
    user_id = db.Column(db.Integer, nullable=True)
    email = db.Column(db.String(255), nullable=False)
    message = db.Column(db.Text, nullable=False)
    status = db.Column(db.String(50), default='sent')
    created_at = db.Column(db.DateTime, default=datetime.utcnow)

    def to_dict(self):
        return {
            'id': self.id,
            'user_id': self.user_id,
            'email': self.email,
            'message': self.message,
            'status': self.status,
            'created_at': self.created_at.isoformat() if self.created_at else None
        }
